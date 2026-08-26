<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $recentCustomers
 */
?>

<div class="card table-card">

    <div class="table-header">
        <div>
            <h3>Recent Customers</h3>
            <p>Latest customers added from Square</p>
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
                <th>Account Created</th>
                <th></th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($recentCustomers as $index => $customer): ?>
                <tr class="<?= $index >= 5 ? 'extra-customer-row' : '' ?>">
                    <td>
                        <?= h(trim(
                            ($customer->given_name ?? '') . ' ' .
                            ($customer->family_name ?? '')
                        )) ?>
                    </td>

                    <td>
                        <?= h($customer->email_address ?? '—') ?>
                    </td>

                    <td>
                        <?= h($customer->phone_number ?? '—') ?>
                    </td>

                    <td>
                        <?php if ($customer->square_created_at): ?>
                            <?= h(
                                $customer->square_created_at
                                    ->format('d/m/Y H:i:s')
                            ) ?>
                        <?php else: ?>
                            —
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


