<?php
/**
 * @var \App\View\AppView $this
 * @var \Square\Types\Customer|null $customer
 */
?>
<h1>Customer detail</h1>

<?php if (!$customer): ?>
    <p>Customer not found.</p>
<?php else: ?>

    <h2>
        <?= h(trim($customer->getGivenName() . ' ' . $customer->getFamilyName())) ?: h($customer->getNickname()) ?: 'Unnamed customer' ?>
    </h2>

    <table>
        <tbody>
            <tr><th>ID</th><td><?= h($customer->getId()) ?></td></tr>
            <tr><th>Email</th><td><?= h($customer->getEmailAddress() ?: '—') ?></td></tr>
            <tr><th>Phone</th><td><?= h($customer->getPhoneNumber() ?: '—') ?></td></tr>
            <tr><th>Company</th><td><?= h($customer->getCompanyName() ?: '—') ?></td></tr>
            <tr><th>Nickname</th><td><?= h($customer->getNickname() ?: '—') ?></td></tr>
            <tr><th>Birthday</th><td><?= h($customer->getBirthday() ?: '—') ?></td></tr>
            <tr><th>Reference ID</th><td><?= h($customer->getReferenceId() ?: '—') ?></td></tr>
            <tr><th>Note</th><td><?= h($customer->getNote() ?: '—') ?></td></tr>
            <tr><th>Creation source</th><td><?= h($customer->getCreationSource() ?: '—') ?></td></tr>
            <tr><th>Version</th><td><?= h($customer->getVersion()) ?></td></tr>
            <tr><th>Created at</th><td><?= h($customer->getCreatedAt()) ?></td></tr>
            <tr><th>Updated at</th><td><?= h($customer->getUpdatedAt()) ?></td></tr>
        </tbody>
    </table>

    <?php $address = $customer->getAddress(); ?>
    <?php if ($address): ?>
        <h3>Address</h3>
        <address>
            <?= h($address->getAddressLine1()) ?><br>
            <?php if ($address->getAddressLine2()): ?>
                <?= h($address->getAddressLine2()) ?><br>
            <?php endif; ?>
            <?= h($address->getLocality()) ?>,
            <?= h($address->getAdministrativeDistrictLevel1()) ?>
            <?= h($address->getPostalCode()) ?>
            <?php if ($address->getCountry()): ?>
                <br><?= h($address->getCountry()) ?>
            <?php endif; ?>
        </address>
    <?php endif; ?>

    <?php $preferences = $customer->getPreferences(); ?>
    <?php if ($preferences): ?>
        <h3>Preferences</h3>
        <p>Email unsubscribed: <?= $preferences->getEmailUnsubscribed() ? 'Yes' : 'No' ?></p>
    <?php endif; ?>

    <?php $groupIds = $customer->getGroupIds(); ?>
    <?php if ($groupIds): ?>
        <h3>Group IDs</h3>
        <ul>
            <?php foreach ($groupIds as $groupId): ?>
                <li><?= h($groupId) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php endif; ?>

<p><?= $this->Html->link('Back to customers', ['controller' => 'Customers', 'action' => 'index']) ?></p>