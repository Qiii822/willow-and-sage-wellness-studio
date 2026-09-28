<?php
/**
 * Everyone Square knows about, one row per person.
 *
 * Pagination is done by hand rather than with the Paginator helper: the
 * rows are people worked out in PHP, not a database query, and the helper
 * only knows how to page a query.
 *
 * @var \App\View\AppView $this
 * @var array<int, array<string, mixed>> $people
 * @var int $total
 * @var int $page
 * @var int $maxPage
 * @var int $first
 * @var int $last
 * @var array<string, string|null> $filters
 * @var array<string, string> $query The filters in use, carried on page links.
 * @var array<string, string> $bookingFilters
 */

$this->assign('title', 'Customers');
$this->setLayout('dashboard');

$hasActiveFilters = $query !== [];
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">

            <div class="top-bar">
                <div>
                    <h1>Customers</h1>
                    <p class="subtitle">
                        Everyone who has booked, synced from Square.
                    </p>
                </div>
            </div>

            <div class="card table-card">

                <div class="table-header">
                    <div>
                        <h3>Customers</h3>
                        <p>
                            <?php if ($total === 0): ?>
                                <?= $hasActiveFilters ? 'No matches' : 'No customers yet' ?>
                            <?php else: ?>
                                Showing <?= $first ?>&ndash;<?= $last ?> of <?= $total ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <form
                        class="customers-filter-form"
                        method="get"
                        action="<?= $this->Url->build(['action' => 'index']) ?>"
                >
                    <div class="filter-field">
                        <label for="customer-search">Search</label>
                        <input
                                type="search"
                                name="q"
                                id="customer-search"
                                placeholder="Name, email or phone"
                                value="<?= h($filters['q']) ?>"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="customer-bookings">Bookings</label>
                        <select
                                name="bookings"
                                id="customer-bookings"
                                onchange="this.form.submit()"
                        >
                            <option value="">Any</option>
                            <?php foreach ($bookingFilters as $value => $label): ?>
                                <option
                                        value="<?= h($value) ?>"
                                        <?= $filters['bookings'] === $value ? 'selected' : '' ?>
                                >
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="customer-start-date">First seen from</label>
                        <input
                                type="date"
                                name="start_date"
                                id="customer-start-date"
                                value="<?= h($filters['start_date'] ?? '') ?>"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="customer-end-date">To</label>
                        <input
                                type="date"
                                name="end_date"
                                id="customer-end-date"
                                value="<?= h($filters['end_date'] ?? '') ?>"
                        >
                    </div>

                    <button class="table-action" type="submit">Filter</button>

                    <?php if ($hasActiveFilters): ?>
                        <a
                                class="filter-clear"
                                href="<?= $this->Url->build(['action' => 'index']) ?>"
                        >
                            Clear filters
                        </a>
                    <?php endif; ?>
                </form>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Bookings</th>
                            <th>First seen</th>
                            <th></th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php if ($people === []): ?>
                            <tr>
                                <td colspan="6">
                                    <?php if ($hasActiveFilters): ?>
                                        No customers match the selected filters.
                                    <?php else: ?>
                                        Nobody has booked yet. Customers appear here
                                        after a sync with Square.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($people as $person): ?>

                            <?php
                            $customer = $person['customer'];
                            $name = trim(
                                ($customer->given_name ?? '') . ' ' .
                                ($customer->family_name ?? '')
                            );
                            ?>

                            <tr>
                                <td>
                                    <?= h($name !== '' ? $name : '-') ?>

                                    <?php /*
                                     * Square makes a new customer for each
                                     * new email address, so one person can
                                     * be several records. Said plainly here
                                     * rather than left as a silent merge.
                                     */ ?>
                                    <?php if ($person['records'] > 1): ?>
                                        <span class="customer-merged">
                                            <?= $person['records'] ?> Square records
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= h($customer->email_address ?: '-') ?>

                                    <?php if (count($person['emails']) > 1): ?>
                                        <span class="customer-merged">
                                            +<?= count($person['emails']) - 1 ?> more
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td><?= h($customer->phone_number ?: '-') ?></td>

                                <td><?= $person['bookings'] ?></td>

                                <td>
                                    <?php if ($customer->square_created_at): ?>
                                        <?= h($customer->square_created_at->format('d/m/Y')) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= $this->Html->link('View', [
                                        'action' => 'view',
                                        $customer->id,
                                    ], ['class' => 'table-action']) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

                <?php if ($maxPage > 1): ?>

                    <nav class="pagination-bar" aria-label="Customer pages">

                        <ul class="pagination">

                            <?php if ($page > 1): ?>
                                <li>
                                    <?= $this->Html->link('&larr; Previous', [
                                        'action' => 'index',
                                        '?' => $query + ['page' => $page - 1],
                                    ], ['escape' => false]) ?>
                                </li>
                            <?php endif; ?>

                            <?php foreach (range(1, $maxPage) as $number): ?>
                                <li<?= $number === $page ? ' class="active"' : '' ?>>
                                    <?= $number === $page
                                        ? '<a href="#">' . $number . '</a>'
                                        : $this->Html->link((string)$number, [
                                            'action' => 'index',
                                            '?' => $query + ['page' => $number],
                                        ]) ?>
                                </li>
                            <?php endforeach; ?>

                            <?php if ($page < $maxPage): ?>
                                <li>
                                    <?= $this->Html->link('Next &rarr;', [
                                        'action' => 'index',
                                        '?' => $query + ['page' => $page + 1],
                                    ], ['escape' => false]) ?>
                                </li>
                            <?php endif; ?>

                        </ul>

                    </nav>

                <?php endif; ?>

            </div>

            <?= $this->Html->link(
                '&larr; Back to Dashboard',
                ['controller' => 'Dashboard', 'action' => 'index'],
                ['escape' => false, 'class' => 'dashboard-back']
            ) ?>

        </main>

    </div>
</div>
