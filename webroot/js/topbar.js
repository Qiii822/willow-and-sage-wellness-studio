document.addEventListener('DOMContentLoaded', function () {

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
            {
                type: 'text/csv;charset=utf-8;'
            }
        );

        const url =
            URL.createObjectURL(blob);

        const link =
            document.createElement('a');

        const selectedDate =
            exportButton.dataset.date || 'customers';

        link.href = url;
        link.download =
            'customers-' + selectedDate + '.csv';

        document.body.appendChild(link);

        link.click();
        link.remove();

        URL.revokeObjectURL(url);
    });

});
