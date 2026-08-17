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

    });
</script>
