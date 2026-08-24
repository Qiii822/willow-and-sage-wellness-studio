<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $customers
 * @var string $source
 * @var int $squareCount
 * @var int $registeredCount
 */

$this->assign('title', 'Customers');
$this->setLayout('dashboard');

$isRegistered = $source === 'registered';

// Keep the selected source on every pagination link, or paging away from
// the registered list would silently drop back to the Square one.
$this->Paginator->options([
    'url' => ['?' => ['source' => $source]],
]);
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Customers</h1>
                    <p class="subtitle">
                        Customers synced from Square, and people who
                        registered on the site.
                    </p>
                </div>
            </div>

            <div class="source-tabs" role="tablist">

                <?= $this->Html->link(
                    'From Square (' . number_format($squareCount) . ')',
                    ['action' => 'index', '?' => ['source' => 'square']],
                    [
                        'class' => 'source-tab' . (!$isRegistered ? ' is-active' : ''),
                        'aria-current' => !$isRegistered ? 'page' : null,
                    ]
                ) ?>

                <?= $this->Html->link(
                    'Registered on site (' . number_format($registeredCount) . ')',
                    ['action' => 'index', '?' => ['source' => 'registered']],
                    [
                        'class' => 'source-tab' . ($isRegistered ? ' is-active' : ''),
                        'aria-current' => $isRegistered ? 'page' : null,
                    ]
                ) ?>

            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3><?= $isRegistered ? 'Registered Accounts' : 'Square Customers' ?></h3>
                        <p>
                            <?= $this->Paginator->counter(
                                'Showing {{start}}&ndash;{{end}} of {{count}} customers'
                            ) ?>
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Account Created</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                            /*
                             * The two sources are different entities, so the
                             * columns are read per source rather than merged.
                             */
                            if ($isRegistered) {
                                $name = trim(
                                    ($customer->first_name ?? '') . ' ' .
                                    ($customer->surname ?? '')
                                );
                                $email = $customer->email;
                                $phone = null;
                                $created = $customer->created_at;
                            } else {
                                $name = trim(
                                    ($customer->given_name ?? '') . ' ' .
                                    ($customer->family_name ?? '')
                                );
                                $email = $customer->email_address;
                                $phone = $customer->phone_number;
                                $created = $customer->square_created_at;
                            }
                            ?>
                            <tr>
                                <td><?= h($name !== '' ? $name : '-') ?></td>
                                <td><?= h($email ?? '-') ?></td>
                                <td><?= h($phone ?? '-') ?></td>
                                <td>
                                    <?php if ($created): ?>
                                        <?= h($created->format('d/m/Y H:i:s')) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($this->Paginator->total() > 1): ?>

                    <nav class="pagination-bar" aria-label="Customer pages">

                        <ul class="pagination">
                            <?= $this->Paginator->prev(
                                '&larr; Previous',
                                ['escape' => false]
                            ) ?>

                            <?= $this->Paginator->numbers([
                                'modulus' => 3,
                                'first' => 1,
                                'last' => 1,
                                'ellipsis' => '<li class="ellipsis">&hellip;</li>',
                            ]) ?>

                            <?= $this->Paginator->next(
                                'Next &rarr;',
                                ['escape' => false]
                            ) ?>
                        </ul>

                        <form
                                class="pagination-jump"
                                method="get"
                                action="<?= $this->Url->build([
                                    'controller' => 'Customers',
                                    'action' => 'index'
                                ]) ?>"
                            >
                                <label for="page-jump">Jump to</label>

                                <input
                                    type="hidden"
                                    name="source"
                                    value="<?= h($source) ?>"
                                >

                                <input
                                    type="number"
                                    id="page-jump"
                                    name="page"
                                    min="1"
                                    max="<?= $this->Paginator->total() ?>"
                                    aria-label="Page number"
                                >

                            <button type="submit">Go</button>
                        </form>

                    </nav>

                <?php endif; ?>

            </div>

            <?= $this->Html->link(
                '&larr; Back to Dashboard',
                [
                    'controller' => 'Dashboard',
                    'action' => 'index'
                ],
                [
                    'escape' => false,
                    'class' => 'dashboard-back'
                ]
            ) ?>

        </main>

    </div>
</div>