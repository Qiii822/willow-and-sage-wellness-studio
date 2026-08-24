<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Model\Entity\User;
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
        if (!empty($user->square_customer_id) || empty($user->email)) {
            return false;
        }

        $matches = $this->customersTable->find()
            ->where([
                'email_address' => $user->email,
                'deleted_at IS' => null,
            ])
            ->limit(2)
            ->all()
            ->toList();

        if (count($matches) !== 1) {
            return false;
        }

        $squareCustomerId = $matches[0]->square_customer_id;

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
