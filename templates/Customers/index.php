<?php
/**
 * @var \App\View\AppView $this
 * @var \Square\Types\Customer[] $customers
 */
?>
<h1>Customers</h1>

<?php if (empty($customers)): ?>
    <p>No customers found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= h($customer->getId()) ?></td>
                    <td><?= h(trim($customer->getGivenName() . ' ' . $customer->getFamilyName())) ?></td>
                    <td><?= h($customer->getEmailAddress()) ?></td>
                    <td><?= h($customer->getPhoneNumber()) ?></td>
                    <td><?= h($customer->getCreatedAt()) ?></td>
                    <td>
                        <?= $this->Html->link('View', ['controller' => 'Customers', 'action' => 'view', $customer->getId()]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>