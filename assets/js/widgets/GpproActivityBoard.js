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
     * @private
     */
    updateColumnCounts() {
        for (const column of this.element.querySelectorAll('.activity_board_column')) {
            const counter = column.querySelector('.activity_board_column_header .badge');
            if (counter === null) {
                continue;
            }
            counter.textContent = String(column.querySelectorAll('.activity_board_card').length);
        }
    }
}
