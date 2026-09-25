<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\SquareAuthService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;

/**
 * Proactively checks the app's Square connection and refreshes its access
 * token if it's due, independent of whether anyone happens to use the app
 * that day.
 *
 * SquareAuthService::getActiveIdentity() already does this same age check
 * and refresh on every retrieval, so day-to-day usage (syncs, admins
 * viewing Settings) already keeps the token current on its own. This
 * command exists purely as a safety net for Square's requirement that
 * refreshing happen "regardless of whether the seller is actively
 * interacting with your application" - if the app sits unused for a
 * stretch, nothing would otherwise call getActiveIdentity() to trigger it.
 *
 * This command does not schedule itself - CakePHP has no built-in
 * scheduler. It needs to be wired into the system's crontab (or
 * equivalent) outside this repo, e.g.:
 *
 *   0 3 * * * cd /path/to/app && bin/cake refresh_square_token >> logs/refresh_square_token.log 2>&1
 */
class RefreshSquareTokenCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Refreshes the Square access token if it is due for a refresh. ' .
            'Intended to be run daily via the system crontab.';
    }

    /**
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $identitiesTable = TableRegistry::getTableLocator()->get('Identities');

        $before = $identitiesTable->getActiveConnection();

        if (!$before) {
            $io->out('No active Square connection - nothing to refresh.');

            return static::CODE_SUCCESS;
        }

        $refreshedAtBefore = $before->refreshed_at;

        // This performs the same age check and refresh that already
        // happens on every normal retrieval - see SquareAuthService for
        // the actual refresh logic. Reusing it here rather than
        // duplicating it means there's exactly one place that decides
        // whether a token is stale and how a refresh is carried out.
        $identity = (new SquareAuthService())->getActiveIdentity();

        if (!$identity) {
            $io->error(
                'Square rejected the refresh token - the connection has been marked as ' .
                'disconnected. An admin needs to reconnect Square from the Settings page.',
            );

            return static::CODE_ERROR;
        }

        $wasRefreshed = $identity->refreshed_at !== null
            && (!$refreshedAtBefore || $identity->refreshed_at->greaterThan($refreshedAtBefore));

        if ($wasRefreshed) {
            $io->out('Square access token refreshed successfully.');
        } else {
            $io->out('Square access token is not due for a refresh yet.');
        }

        return static::CODE_SUCCESS;
    }
}
