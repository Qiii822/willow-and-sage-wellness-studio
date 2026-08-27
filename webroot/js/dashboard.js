document.addEventListener('DOMContentLoaded', function () {

    const dashboard =
        document.getElementById('dashboard-widgets');

    const editButton =
        document.getElementById('edit-layout-btn');

    const addButton =
        document.getElementById('add-widget-btn');

    const modal =
        document.getElementById('widget-modal');

    const modalClose =
        document.getElementById('widget-modal-close');

    const modalOverlay =
        document.getElementById('widget-modal-overlay');

    const libraryItems =
        document.querySelectorAll(
            '.widget-library-item'
        );

    const rangeSelect =
        document.getElementById(
            'dashboard-range-select'
        );

    const filterPanels =
        document.querySelectorAll(
            '[data-filter-panel]'
        );

    const filterApplyButton =
        document.getElementById(
            'dashboard-filter-apply'
        );

    const STORAGE_KEY =
        'wendy-dashboard-layout';

    let editing = false;
    let draggedWidget = null;


    /* =====================================================
       Lucide
       ===================================================== */

    function refreshIcons() {

        if (window.lucide) {
            lucide.createIcons();
        }

    }


    /* =====================================================
       Dashboard range filters
       ===================================================== */

    function updateRangeFilter() {

        if (!rangeSelect) {
            return;
        }

        const selectedRange =
            rangeSelect.value;

        filterPanels.forEach(function (panel) {

            const active =
                panel.dataset.filterPanel ===
                selectedRange;

            panel.classList.toggle(
                'show',
                active
            );

            panel
                .querySelectorAll(
                    'input, select, button'
                )
                .forEach(function (control) {

                    control.disabled =
                        !active;

                });

        });


        /*
         * Today requires no additional
         * filter controls.
         */
        if (filterApplyButton) {

            filterApplyButton.style.display =
                selectedRange === 'today'
                    ? 'none'
                    : 'inline-flex';

        }

    }


    rangeSelect?.addEventListener(
        'change',
        function () {

            updateRangeFilter();

            /*
             * Today immediately reloads
             * the dashboard.
             */
            if (rangeSelect.value === 'today') {

                rangeSelect.form.submit();

            }

        }
    );


    updateRangeFilter();


    /* =====================================================
       Widget Library
       ===================================================== */

    function openWidgetLibrary() {

        modal?.classList.add('open');

        updateWidgetLibrary();

    }


    function closeWidgetLibrary() {

        modal?.classList.remove('open');

    }


    addButton?.addEventListener(
        'click',
        openWidgetLibrary
    );


    modalClose?.addEventListener(
        'click',
        closeWidgetLibrary
    );


    modalOverlay?.addEventListener(
        'click',
        closeWidgetLibrary
    );


    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {
                closeWidgetLibrary();
            }

        }
    );


    /* =====================================================
       Add Widget
       ===================================================== */

    libraryItems.forEach(function (item) {

        item.addEventListener(
            'click',
            function () {

                const widgetName =
                    item.dataset.widget;

                const widget =
                    dashboard?.querySelector(
                        '[data-widget="' +
                        widgetName +
                        '"]'
                    );

                if (!widget) {
                    return;
                }

                widget.classList.remove(
                    'widget-hidden'
                );

                saveLayout();

                updateWidgetLibrary();

                closeWidgetLibrary();

            }
        );

    });


    /* =====================================================
       Edit Layout
       ===================================================== */

    editButton?.addEventListener(
        'click',
        function () {

            editing = !editing;

            dashboard?.classList.toggle(
                'editing',
                editing
            );


            if (editing) {

                editButton.innerHTML =
                    '<i data-lucide="check"></i>' +
                    ' Finish editing';

            } else {

                editButton.innerHTML =
                    '<i data-lucide="pencil"></i>' +
                    ' Edit layout';

                saveLayout();

            }


            const widgets =
                dashboard?.querySelectorAll(
                    '.dashboard-widget'
                );

            widgets?.forEach(
                function (widget) {

                    widget.draggable =
                        editing;

                }
            );


            refreshIcons();

        }
    );


    /* =====================================================
       Remove Widget
       ===================================================== */

    document
        .querySelectorAll(
            '.widget-remove-btn'
        )
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    if (!editing) {
                        return;
                    }

                    const widget =
                        button.closest(
                            '.dashboard-widget'
                        );

                    if (!widget) {
                        return;
                    }

                    widget.classList.add(
                        'widget-hidden'
                    );

                    saveLayout();

                    updateWidgetLibrary();

                }
            );

        });


    /* =====================================================
       Drag Start
       ===================================================== */

    dashboard?.addEventListener(
        'dragstart',
        function (event) {

            if (!editing) {

                event.preventDefault();

                return;
            }

            const widget =
                event.target.closest(
                    '.dashboard-widget'
                );

            if (!widget) {
                return;
            }

            draggedWidget = widget;

            draggedWidget.classList.add(
                'dragging'
            );

            event.dataTransfer.effectAllowed =
                'move';

        }
    );


    /* =====================================================
       Drag Over
       ===================================================== */

    dashboard?.addEventListener(
        'dragover',
        function (event) {

            if (
                !editing ||
                !draggedWidget
            ) {
                return;
            }

            event.preventDefault();

            const slot =
                event.target.closest(
                    '.dashboard-drop-slot'
                );


            document
                .querySelectorAll(
                    '.dashboard-drop-slot'
                )
                .forEach(function (item) {

                    item.classList.remove(
                        'drag-over'
                    );

                });


            if (slot) {

                slot.classList.add(
                    'drag-over'
                );

            }

        }
    );


    /* =====================================================
       Drop
       ===================================================== */

    dashboard?.addEventListener(
        'drop',
        function (event) {

            if (
                !editing ||
                !draggedWidget
            ) {
                return;
            }

            event.preventDefault();


            const slot =
                event.target.closest(
                    '.dashboard-drop-slot'
                );

            if (!slot) {
                return;
            }


            if (
                !draggedWidget.classList.contains(
                    'widget-small'
                )
            ) {
                return;
            }


            const newSlot =
                slot.dataset.slot;

            const oldSlot =
                draggedWidget.dataset.slot;


            const existingWidget =
                dashboard.querySelector(
                    '.dashboard-widget.widget-small' +
                    '[data-slot="' +
                    newSlot +
                    '"]'
                );


            if (
                existingWidget &&
                existingWidget !== draggedWidget
            ) {

                existingWidget.dataset.slot =
                    oldSlot;

            }


            draggedWidget.dataset.slot =
                newSlot;


            updateWidgetPositions();

            saveLayout();

            clearDropHighlights();

        }
    );


    /* =====================================================
       Drag End
       ===================================================== */

    dashboard?.addEventListener(
        'dragend',
        function () {

            if (draggedWidget) {

                draggedWidget.classList.remove(
                    'dragging'
                );

            }

            draggedWidget = null;

            clearDropHighlights();

            saveLayout();

        }
    );


    /* =====================================================
       Clear Drop Highlights
       ===================================================== */

    function clearDropHighlights() {

        document
            .querySelectorAll(
                '.dashboard-drop-slot'
            )
            .forEach(function (slot) {

                slot.classList.remove(
                    'drag-over'
                );

            });

    }


    /* =====================================================
       Widget Positions
       ===================================================== */

    function updateWidgetPositions() {

        if (!dashboard) {
            return;
        }


        const smallWidgets =
            dashboard.querySelectorAll(
                '.dashboard-widget.widget-small'
            );


        smallWidgets.forEach(
            function (widget) {

                const slot =
                    widget.dataset.slot;

                if (!slot) {
                    return;
                }

                widget.style.gridColumn =
                    slot + ' / span 1';

                widget.style.gridRow =
                    '1';

            }
        );


        const slots =
            dashboard.querySelectorAll(
                '.dashboard-drop-slot'
            );


        slots.forEach(
            function (slot) {

                const slotNumber =
                    slot.dataset.slot;

                slot.style.gridColumn =
                    slotNumber + ' / span 1';

                slot.style.gridRow =
                    '1';

            }
        );


        const recentCustomers =
            dashboard.querySelector(
                '[data-widget="recent-customers"]'
            );


        if (recentCustomers) {

            recentCustomers.style.gridColumn =
                '1 / -1';

            recentCustomers.style.gridRow =
                '2';

        }

    }


    /* =====================================================
       Save Layout
       ===================================================== */

    function saveLayout() {

        if (!dashboard) {
            return;
        }


        const widgets =
            dashboard.querySelectorAll(
                '.dashboard-widget'
            );


        const layout =
            Array
                .from(widgets)
                .map(function (widget) {

                    return {

                        name:
                        widget.dataset.widget,

                        visible:
                            !widget
                                .classList
                                .contains(
                                    'widget-hidden'
                                ),

                        slot:
                            widget.dataset.slot ||
                            null

                    };

                });


        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(layout)
        );

    }


    /* =====================================================
       Load Layout
       ===================================================== */

    function loadLayout() {

        if (!dashboard) {
            return;
        }


        const saved =
            localStorage.getItem(
                STORAGE_KEY
            );

        if (!saved) {
            return;
        }


        let layout;

        try {

            layout =
                JSON.parse(saved);

        } catch (error) {

            return;

        }


        layout.forEach(
            function (savedWidget) {

                const widget =
                    dashboard.querySelector(
                        '[data-widget="' +
                        savedWidget.name +
                        '"]'
                    );

                if (!widget) {
                    return;
                }


                widget.classList.toggle(
                    'widget-hidden',
                    !savedWidget.visible
                );


                if (savedWidget.slot) {

                    widget.dataset.slot =
                        savedWidget.slot;

                }

            }
        );

    }


    /* =====================================================
       Widget Library Status
       ===================================================== */

    function updateWidgetLibrary() {

        libraryItems.forEach(
            function (item) {

                const widgetName =
                    item.dataset.widget;


                const widget =
                    dashboard?.querySelector(
                        '[data-widget="' +
                        widgetName +
                        '"]'
                    );


                if (!widget) {
                    return;
                }


                const visible =
                    !widget
                        .classList
                        .contains(
                            'widget-hidden'
                        );


                item.classList.toggle(
                    'already-added',
                    visible
                );

            }
        );

    }


    /* =====================================================
       Initialise
       ===================================================== */

    loadLayout();

    updateWidgetPositions();

    updateWidgetLibrary();

    refreshIcons();

});
