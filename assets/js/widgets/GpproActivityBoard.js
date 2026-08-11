/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [GPPRO] GpproActivityBoard: drag-and-drop kanban board for project activities
 */

import Sortable from 'sortablejs';

export default class GpproActivityBoard {

    /**
     * @param {GpproContainer} gppro
     * @param {HTMLElement} element
     */
    constructor(gppro, element) {
        this.gppro = gppro;
        this.element = element;
        this.updateUrlTemplate = element.dataset.updateUrl;
        this.editUrlTemplate = element.dataset.editUrl;
        this.sortables = [];

        const columns = element.querySelectorAll('.activity_board_column_body');
        for (const column of columns) {
            this.sortables.push(Sortable.create(column, {
                group: 'activity_board',
                animation: 150,
                ghostClass: 'activity_board_card_ghost',
                onEnd: (event) => this.onCardMoved(event),
            }));
        }

        this.element.addEventListener('dblclick', (event) => this.onCardDoubleClicked(event));

        this.searchInput = document.getElementById('activity_board_search');
        this.filters = {
            status: document.getElementById('activity_board_status_filter'),
            priority: document.getElementById('activity_board_priority_filter'),
            assignee: document.getElementById('activity_board_assignee_filter'),
        };
        this.populateAssigneeFilter();

        if (this.searchInput !== null) {
            this.searchInput.addEventListener('input', () => this.filterCards());
        }
        for (const filter of Object.values(this.filters)) {
            if (filter !== null) {
                filter.addEventListener('change', () => this.filterCards());
            }
        }
    }

    /**
     * Opens the activity edit modal for the double-clicked card.
     *
     * @param {MouseEvent} event
     * @private
     */
    onCardDoubleClicked(event) {
        const card = event.target.closest('.activity_board_card');
        if (card === null || this.editUrlTemplate === undefined) {
            return;
        }

        const url = this.editUrlTemplate.replace('000', card.dataset.activityId);

        /** @type {GpproAjaxModalForm} */
        const modal = this.gppro.getPlugin('modal');
        modal.openUrlInModal(url);
    }

    /**
     * @param {string} selector
     * @param {GpproContainer} gppro
     * @returns {GpproActivityBoard|null}
     */
    static create(selector, gppro) {
        const element = document.querySelector(selector);
        if (element === null) {
            return null;
        }

        return new GpproActivityBoard(gppro, element);
    }

    /**
     * SortableJS already moved the card in the DOM by the time "onEnd" fires,
     * so a rejected request is handled by reinserting the card at its
     * recorded origin column and index (apply-then-rollback), instead of
     * blocking the drag or waiting for the response before moving anything.
     *
     * @param {object} event SortableJS SortableEvent
     * @private
     */
    onCardMoved(event) {
        const originColumn = event.from;
        const targetColumn = event.to;

        if (originColumn === targetColumn) {
            // Reordering within a column has no persisted effect: the server
            // always re-sorts a column by priority/due date/name (see the
            // board's card ordering requirement), so there is nothing to
            // send and nothing that could be rejected/rolled back here.
            return;
        }

        const card = event.item;
        const activityId = card.dataset.activityId;
        const originIndex = event.oldIndex;
        const originNextSibling = originColumn.children[originIndex] ?? null;
        const newStatus = targetColumn.closest('.activity_board_column').dataset.status;

        this.toggleColumnEmptyState(originColumn);
        this.toggleColumnEmptyState(targetColumn);
        this.updateColumnCounts();

        /** @type {GpproAPI} API */
        const API = this.gppro.getPlugin('api');
        const url = this.updateUrlTemplate.replace('000', activityId);

        API.patch(url, JSON.stringify({status: newStatus}), () => {
            // server truth matches the already-applied DOM state, nothing else to do
        }, (error) => {
            this.revertCard(card, originColumn, originNextSibling);
            API.handleError('action.update.error', error);
        });
    }

    /**
     * Reinserts a card at its original column/position after a rejected move.
     *
     * @param {HTMLElement} card
     * @param {HTMLElement} originColumn
     * @param {HTMLElement|null} originNextSibling
     * @private
     */
    revertCard(card, originColumn, originNextSibling) {
        const rejectedColumn = card.parentElement;

        if (originNextSibling !== null && originNextSibling.parentNode === originColumn) {
            originColumn.insertBefore(card, originNextSibling);
        } else {
            originColumn.appendChild(card);
        }

        this.toggleColumnEmptyState(originColumn);
        if (rejectedColumn !== null && rejectedColumn !== originColumn) {
            this.toggleColumnEmptyState(rejectedColumn);
        }
        this.updateColumnCounts();
    }

    /**
     * Shows/hides the server-rendered "no activities" placeholder as cards
     * enter or leave a column, without touching the column's markup shape.
     *
     * @param {HTMLElement} columnBody
     * @private
     */
    toggleColumnEmptyState(columnBody) {
        const placeholder = columnBody.querySelector('.activity_board_column_empty');
        const hasCards = columnBody.querySelectorAll('.activity_board_card').length > 0;

        if (placeholder !== null) {
            placeholder.style.display = hasCards ? 'none' : '';
        }
    }

    /**
     * Re-applies the current search filter, which recomputes every column's
     * visible-card count as a side effect - kept as the single source of
     * truth for counts so a drag-drop move never shows a stale total while
     * a filter is active.
     *
     * @private
     */
    updateColumnCounts() {
        this.filterCards();
    }

    populateAssigneeFilter() {
        const filter = this.filters.assignee;
        if (filter === null) {
            return;
        }

        const assignees = new Map();
        for (const card of this.element.querySelectorAll('.activity_board_card')) {
            const values = (card.dataset.assignees ?? '').split('|').filter(Boolean);
            const labels = (card.dataset.assigneeLabels ?? '').split('|');
            values.forEach((value, index) => assignees.set(value, labels[index] ?? value));
        }

        for (const [value, label] of [...assignees.entries()].sort((a, b) => a[1].localeCompare(b[1]))) {
            filter.add(new Option(label, value));
        }
        filter.add(new Option(filter.dataset.unassignedLabel ?? 'Unassigned', '__unassigned__'));
    }

    /**
     * Filters cards using server-rendered card metadata purely in the DOM -
     * the board already renders every card up front, so no request is needed.
     *
     * @private
     */
    filterCards() {
        const needle = this.searchInput === null ? '' : this.searchInput.value.trim().toLowerCase();
        const status = this.filters.status === null ? '' : this.filters.status.value;
        const priority = this.filters.priority === null ? '' : this.filters.priority.value;
        const assignee = this.filters.assignee === null ? '' : this.filters.assignee.value;

        for (const column of this.element.querySelectorAll('.activity_board_column')) {
            const cards = column.querySelectorAll('.activity_board_card');
            let visibleCount = 0;

            for (const card of cards) {
                const cardAssignees = card.dataset.assignees ?? '';
                const matchesSearch = needle === '' || (card.dataset.search ?? '').includes(needle);
                const matchesStatus = status === '' || card.dataset.status === status;
                const matchesPriority = priority === '' || card.dataset.priority === priority;
                const matchesAssignee = assignee === ''
                    || (assignee === '__unassigned__' ? cardAssignees === '' : cardAssignees.split('|').includes(assignee));
                const matches = matchesSearch && matchesStatus && matchesPriority && matchesAssignee;
                card.classList.toggle('activity_board_card_filtered', !matches);
                if (matches) {
                    visibleCount++;
                }
            }

            const counter = column.querySelector('.activity_board_column_header .badge');
            if (counter !== null) {
                counter.textContent = String(visibleCount);
            }

            const noMatches = column.querySelector('.activity_board_column_no_matches');
            if (noMatches !== null) {
                noMatches.classList.toggle('d-none', !(cards.length > 0 && visibleCount === 0));
            }
        }
    }
}
