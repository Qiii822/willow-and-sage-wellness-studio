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

    const SIZE_CLASSES = [
        'widget-small',
        'widget-medium',
        'widget-large',
        'widget-wide'
    ];

    const SIZE_NAMES = [
        'small',
        'medium',
        'large',
        'wide'
    ];

    const GRID_COLUMNS = 4;
    const GRID_GAP = 24;

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

            if (
                !editing ||
                event.target.closest(
                    '.widget-resize-handle'
                )
            ) {

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


            draggedWidget =
                widget;


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


            const target =
                findDropTarget(
                    event.clientX,
                    event.clientY
                );


            if (!target.element) {

                dashboard.appendChild(
                    draggedWidget
                );

                return;

            }


            if (
                target.element ===
                draggedWidget
            ) {
                return;
            }


            if (target.after) {

                target.element.after(
                    draggedWidget
                );

            } else {

                target.element.before(
                    draggedWidget
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

            saveLayout();

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

            saveLayout();

        }
    );


    /* =====================================================
       Find Drop Target
       ===================================================== */

    function findDropTarget(x, y) {

        if (!dashboard) {

            return {
                distance: Infinity,
                element: null,
                after: false
            };

        }


        const candidates =
            Array.from(
                dashboard.querySelectorAll(
                    '.dashboard-widget:not(.widget-hidden)'
                )
            ).filter(function (widget) {

                return widget !== draggedWidget;

            });


        let closest = {
            distance: Infinity,
            element: null,
            after: false
        };


        candidates.forEach(function (widget) {

            const box =
                widget.getBoundingClientRect();

            const centerX =
                box.left +
                box.width / 2;

            const centerY =
                box.top +
                box.height / 2;

            const distance =
                Math.hypot(
                    x - centerX,
                    y - centerY
                );


            if (distance < closest.distance) {

                const sameRow =
                    Math.abs(
                        y - centerY
                    ) <
                    box.height / 2;


                closest = {
                    distance: distance,
                    element: widget,
                    after: sameRow
                        ? x > centerX
                        : y > centerY
                };

            }

        });


        return closest;

    }


    /* =====================================================
       Widget Resize
       ===================================================== */

    function sizeIndexOf(widget) {

        for (
            let i = 0;
            i < SIZE_CLASSES.length;
            i++
        ) {

            if (
                widget.classList.contains(
                    SIZE_CLASSES[i]
                )
            ) {

                return i;

            }

        }


        return 0;

    }


    function applySizeIndex(widget, index) {

        const clamped =
            Math.min(
                SIZE_CLASSES.length - 1,
                Math.max(0, index)
            );


        SIZE_CLASSES.forEach(
            function (className) {

                widget.classList.remove(
                    className
                );

            }
        );


        widget.classList.add(
            SIZE_CLASSES[clamped]
        );

    }


    function columnWidth() {

        if (!dashboard) {
            return 0;
        }


        const containerWidth =
            dashboard
                .getBoundingClientRect()
                .width;


        return (
            containerWidth -
            GRID_GAP *
            (GRID_COLUMNS - 1)
        ) / GRID_COLUMNS;

    }


    document
        .querySelectorAll(
            '.widget-resize-handle'
        )
        .forEach(function (handle) {

            handle.addEventListener(
                'mousedown',
                function (event) {

                    if (!editing) {
                        return;
                    }


                    event.preventDefault();
                    event.stopPropagation();


                    const widget =
                        handle.closest(
                            '.dashboard-widget'
                        );


                    if (!widget) {
                        return;
                    }


                    const startX =
                        event.clientX;

                    const startWidth =
                        widget
                            .getBoundingClientRect()
                            .width;

                    const colWidth =
                        columnWidth();


                    if (colWidth <= 0) {
                        return;
                    }


                    widget.classList.add(
                        'resizing'
                    );


                    function onMove(moveEvent) {

                        const deltaX =
                            moveEvent.clientX -
                            startX;

                        const newWidth =
                            startWidth +
                            deltaX;


                        let span =
                            Math.round(
                                (
                                    newWidth +
                                    GRID_GAP
                                ) /
                                (
                                    colWidth +
                                    GRID_GAP
                                )
                            );


                        span =
                            Math.min(
                                GRID_COLUMNS,
                                Math.max(
                                    1,
                                    span
                                )
                            );


                        applySizeIndex(
                            widget,
                            span - 1
                        );

                    }


                    function onUp() {

                        document.removeEventListener(
                            'mousemove',
                            onMove
                        );

                        document.removeEventListener(
                            'mouseup',
                            onUp
                        );


                        widget.classList.remove(
                            'resizing'
                        );


                        saveLayout();

                    }


                    document.addEventListener(
                        'mousemove',
                        onMove
                    );

                    document.addEventListener(
                        'mouseup',
                        onUp
                    );

                }
            );

        });


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
                .map(
                    function (
                        widget,
                        index
                    ) {

                        return {

                            name:
                            widget
                                .dataset
                                .widget,

                            visible:
                                !widget
                                    .classList
                                    .contains(
                                        'widget-hidden'
                                    ),

                            size:
                                SIZE_NAMES[
                                    sizeIndexOf(
                                        widget
                                    )
                                    ],

                            order:
                            index

                        };

                    }
                );


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


        if (!Array.isArray(layout)) {
            return;
        }


        /*
         * Sort using the saved widget order.
         */
        layout.sort(
            function (a, b) {

                return (
                    (a.order ?? 0) -
                    (b.order ?? 0)
                );

            }
        );


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


                const sizeIndex =
                    SIZE_NAMES.indexOf(
                        savedWidget.size
                    );


                if (sizeIndex !== -1) {

                    applySizeIndex(
                        widget,
                        sizeIndex
                    );

                }


                /*
                 * DOM order controls the
                 * dashboard position.
                 */
                dashboard.appendChild(
                    widget
                );

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

    updateWidgetLibrary();

    refreshIcons();

});
