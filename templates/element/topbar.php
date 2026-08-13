<?php
/**
 * @var \App\View\AppView $this
 * @var string $selectedDate
 * @var int $customersSelectedDate
 */

$today = \Cake\I18n\FrozenDate::today()
    ->format('Y-m-d');
?>

<div class="top-bar">
    <div>
        <h1>Welcome back, Wendy</h1>
        <p class="subtitle">
            Here's what's happening with your business today.
        </p>
    </div>

    <div class="top-actions">

        <div class="notification-wrapper">
            <button
                    class="notification"
                    type="button"
                    id="notification-button"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-label="Notifications"
            >
                <i data-lucide="bell"></i>
            </button>

            <div class="notification-modal" id="notification-modal">
                <div class="notification-header">
                    <div>
                        <h4>Notifications</h4>
                        <span>Dashboard activity</span>
                    </div>

                    <button
                            type="button"
                            class="notification-close"
                            id="notification-close"
                    >
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <div class="notification-item">
                    <div class="notification-item-icon">
                        <i data-lucide="user-plus"></i>
                    </div>

                    <div>
                        <strong>
                            <?= number_format($customersSelectedDate) ?>
                            new customer<?= $customersSelectedDate === 1 ? '' : 's' ?>
                        </strong>
                        <p>Added on the selected date</p>
                    </div>
                </div>
            </div>
        </div>

        <form
                class="date-filter-form"
                method="get"
                action="<?= $this->Url->build([
                    'controller' => 'Dashboard',
                    'action' => 'index'
                ]) ?>"
        >
            <input
                    type="date"
                    name="date"
                    max="<?= h($today) ?>"
                    value="<?= h($selectedDate ?? $today) ?>"
                    onchange="this.form.submit()"
            >
        </form>

        <button class="export-btn" type="button" id="export-customers">
            <i data-lucide="download"></i>
            Export customers
        </button>

        <?= $this->Html->link(
            'W',
            [
                'controller' => 'Users',
                'action' => 'profile'
            ],
            [
                'class' => 'profile'
            ]
        ) ?>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        // =========================================
        // Export customers
        // =========================================

        const exportButton =
            document.getElementById('export-customers');

        exportButton?.addEventListener('click', function () {

            const table =
                document.querySelector('.dashboard-table');

            if (!table) {
                return;
            }

            const rows =
                Array.from(table.querySelectorAll('tr'));

            const csvRows = rows.map(function (row) {

                return Array.from(
                    row.querySelectorAll('th, td')
                )
                    .map(function (cell) {

                        const value = cell.innerText
                            .trim()
                            .replace(/"/g, '""');

                        return '"' + value + '"';
                    })
                    .join(',');
            });

            const csv = csvRows.join('\n');

            const blob = new Blob(
                [csv],
                {type: 'text/csv;charset=utf-8;'}
            );

            const url = URL.createObjectURL(blob);

            const link = document.createElement('a');

            link.href = url;
            link.download = 'customers-<?= h($selectedDate ?? $today) ?>.csv';

            document.body.appendChild(link);
            link.click();
            link.remove();

            URL.revokeObjectURL(url);
        });


        // =========================================
        // Notification dropdown
        // =========================================

        const notificationWrapper = document.querySelector('.notification-wrapper');
        const notificationButton  = document.getElementById('notification-button');
        const notificationModal   = document.getElementById('notification-modal');
        const notificationClose   = document.getElementById('notification-close');

        function openNotifications() {
            notificationModal?.classList.add('is-open');
            notificationButton?.setAttribute('aria-expanded', 'true');
        }

        function closeNotifications() {
            notificationModal?.classList.remove('is-open');
            notificationButton?.setAttribute('aria-expanded', 'false');
        }

        notificationButton?.addEventListener('click', function (event) {
            event.stopPropagation();
            notificationModal?.classList.contains('is-open')
                ? closeNotifications()
                : openNotifications();
        });

        notificationClose?.addEventListener('click', closeNotifications);

        document.addEventListener('click', function (event) {
            if (notificationWrapper && !notificationWrapper.contains(event.target)) {
                closeNotifications();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeNotifications();
        });

    });
</script>
