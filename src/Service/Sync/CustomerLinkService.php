<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Model\Entity\User;
use App\Utility\PhoneNumber;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Matches local login accounts to their record in the Square customer mirror.
 *
 * The two tables have separate owners: `users` is written by this site
 * (registration, admin screens), `customers` is written only by
 * CustomerSyncService pulling from Square. Nothing merges them - this
 * service just records which row in one belongs to which row in the other,
 * by storing the Square customer id on the user.
 *
 * Email is the only field both sides share, so it is the join key. A
 * booking made on Square carries whatever email the customer typed there,
 * which is why the link forms after the fact rather than at signup.
 */
class CustomerLinkService
{
    protected \Cake\ORM\Table $usersTable;
    protected \Cake\ORM\Table $customersTable;

    public function __construct()
    {
        $this->usersTable = TableRegistry::getTableLocator()->get('Users');
        $this->customersTable = TableRegistry::getTableLocator()->get('Customers');
    }

    /**
     * Attempts to link a single account to a Square customer.
     *
     * Only an unambiguous match is accepted. Square does not enforce unique
     * emails on its customers, so if two live records share this address
     * there is no way to tell which person the account belongs to, and
     * guessing would attach someone's bookings to the wrong login. Those
     * are left unlinked for an admin to resolve.
     *
     * @param \App\Model\Entity\User $user The account to link.
     * @return bool True if the account was linked by this call.
     */
    public function linkUser(User $user): bool
    {
        // Already linked, or nothing to match on.
        if (!empty($user->square_customer_id)) {
            return false;
        }

        /*
         * Mobile first. Square's booking checkout treats the mobile as the
         * buyer's account - it is what they sign back in with - so a
         * returning customer reliably enters the same number, whereas the
         * email is just another field they may fill differently.
         */
        $squareCustomerId = $this->findByPhone($user)
            ?? $this->findByEmail($user);

        if ($squareCustomerId === null) {
            return false;
        }

        /*
         * Another account already claims this Square customer. The column is
         * unique, so saving would throw - skip instead, and let an admin sort
         * out which of the two logins is the real one.
         */
        $claimed = $this->usersTable->find()
            ->where([
                'square_customer_id' => $squareCustomerId,
                'id !=' => $user->id,
            ])
            ->first();

        if ($claimed) {
            return false;
        }

        /*
         * Set directly rather than through patchEntity: square_customer_id is
         * kept out of the User entity's accessible list so that no submitted
         * form can ever assign it. Only this service may.
         */
        $user->square_customer_id = $squareCustomerId;

        return (bool)$this->usersTable->save($user);
    }

    /**
     * The Square customer whose mobile is this account's mobile.
     *
     * Square stores numbers punctuated (`+61 415 555 365`) while accounts
     * hold E.164, so the stored text is stripped down before comparing.
     * That rules out an index, which is fine at this table's size and is the
     * price of not adding a derived column to a table the sync owns.
     *
     * @param \App\Model\Entity\User $user
     * @return string|null The Square customer id, or null if not exactly one match.
     */
    protected function findByPhone(User $user): ?string
    {
        $phone = PhoneNumber::normalise($user->phone ?? null);

        if ($phone === null) {
            return null;
        }

        // Compare on digits alone: +61415555365 vs the stored +61 415 555 365.
        $bare = ltrim($phone, '+');

        $matches = $this->customersTable->find()
            ->where([
                'deleted_at IS' => null,
                "REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '-', ''), '+', '') =" => $bare,
            ])
            ->limit(2)
            ->all()
            ->toList();

        return count($matches) === 1 ? $matches[0]->square_customer_id : null;
    }

    /**
     * The Square customer whose email is this account's email.
     *
     * @param \App\Model\Entity\User $user
     * @return string|null The Square customer id, or null if not exactly one match.
     */
    protected function findByEmail(User $user): ?string
    {
        if (empty($user->email)) {
            return null;
        }

        $matches = $this->customersTable->find()
            ->where([
                'email_address' => $user->email,
                'deleted_at IS' => null,
            ])
            ->limit(2)
            ->all()
            ->toList();

        return count($matches) === 1 ? $matches[0]->square_customer_id : null;
    }

    /**
     * Sweeps every account that is still unlinked and tries to match it.
     *
     * Run after a sync, when customer records that did not exist locally a
     * moment ago have just arrived.
     *
     * @return int How many accounts were linked.
     */
    public function linkUnlinkedUsers(): int
    {
        $linked = 0;

        $users = $this->usersTable->find()
            ->where(['square_customer_id IS' => null])
            ->all();

        foreach ($users as $user) {
            try {
                if ($this->linkUser($user)) {
                    $linked++;
                }
            } catch (\Throwable $e) {
                // One bad row must not abandon the rest of the sweep.
                Log::error(sprintf(
                    'Could not link user %s to a Square customer: %s',
                    $user->id,
                    $e->getMessage()
                ));
            }
        }

        return $linked;
    }
}
