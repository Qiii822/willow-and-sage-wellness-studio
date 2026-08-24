<?php
/**
 * @var \App\View\AppView $this
 * @var array<int, array<string, mixed>> $recentCustomers
 */
?>

<div class="card table-card">

    <div class="table-header">
        <div>
            <h3>Recent Customers</h3>
            <p>Synced from Square and registered on the site</p>
        </div>

        <?= $this->Html->link(
            'View all',
            [
                'controller' => 'Customers',
                'action' => 'index',
            ],
            [
                'class' => 'table-action',
            ]
        ) ?>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table">
            <thead>
            <tr>
                <th>Customer</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Source</th>
                <th>Account Created</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($recentCustomers as $index => $customer): ?>
                <tr class="<?= $index >= 5 ? 'extra-customer-row' : '' ?>">
                    <td>
                        <?= h($customer['name'] !== '' ? $customer['name'] : '—') ?>
                    </td>

                    <td>
                        <?= h($customer['email'] ?? '—') ?>
                    </td>

                    <td>
                        <?= h($customer['phone'] ?? '—') ?>
                    </td>

                    <td>
                        <?php if ($customer['source'] === 'account'): ?>
                            <span class="source-badge is-account">Registered</span>
                        <?php else: ?>
                            <span class="source-badge">Square</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($customer['created']): ?>
                            <?= h($customer['created']->format('d/m/Y H:i:s')) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
