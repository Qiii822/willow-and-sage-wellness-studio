document.addEventListener('DOMContentLoaded', function () {

    const button =
        document.getElementById('view-all-customers');

    const extraRows =
        document.querySelectorAll('.extra-customer-row');

    if (!button) {
        return;
    }

    let expanded = false;

    extraRows.forEach(function (row) {
        row.style.display = 'none';
    });

    button.addEventListener('click', function () {

        expanded = !expanded;

        extraRows.forEach(function (row) {
            row.style.display =
                expanded ? 'table-row' : 'none';
        });

        button.textContent =
            expanded ? 'Show less' : 'View all';
    });

});
