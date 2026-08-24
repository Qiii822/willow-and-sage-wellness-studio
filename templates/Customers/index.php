<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $customers
 */

$this->assign('title', 'Customers');
$this->setLayout('dashboard');
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Customers</h1>
                    <p class="subtitle">All customers added from Square.</p>
                </div>
            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3>All Customers</h3>
                        <p><?= count($customers) ?> customers</p>
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
                            <th></th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <?= h(trim(
                                        ($customer->given_name ?? '') . ' ' .
                                        ($customer->family_name ?? '')
                                    )) ?>
                                </td>
                                <td><?= h($customer->email_address ?? '-') ?></td>
                                <td><?= h($customer->phone_number ?? '-') ?></td>
                                <td>
                                    <?php if ($customer->square_created_at): ?>
                                        <?= h($customer->square_created_at->format('d/m/Y H:i:s')) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $this->Html->link(
                                        'View',
                                        [
                                            'controller' => 'Customers',
                                            'action' => 'view',
                                            $customer->id,
                                        ],
                                        [
                                            'class' => 'table-action',
                                        ]
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

    </div>
</div>