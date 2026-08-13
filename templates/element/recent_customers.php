<div class="card table-card">

    <div class="table-header">
        <div>
            <h3>Recent Customers</h3>
            <p>Latest customers added from Square</p>
        </div>

        <button
            type="button"
            class="table-action"
            id="view-all-customers"
        >
            View all
        </button>
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
            <?php foreach ($recentCustomers as $index => $customer): ?>
                <tr
                    class="<?= $index >= 5 ? 'extra-customer-row' : '' ?>"
                >
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
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const button = document.getElementById('view-all-customers');
        const extraRows = document.querySelectorAll('.extra-customer-row');

        if (!button) {
            return;
        }

        let expanded = false;

        button.addEventListener('click', function () {
            expanded = !expanded;

            extraRows.forEach(function (row) {
                row.style.display = expanded ? 'table-row' : 'none';
            });

            button.textContent = expanded ? 'Show less' : 'View all';
        });

        extraRows.forEach(function (row) {
            row.style.display = 'none';
        });
    });
</script>
