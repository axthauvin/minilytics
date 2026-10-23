<!-- Page: Events Stream & Explorer -->
<div class="page-view" id="page-events">
    <div class="page-header-box">
        <div class="page-intro">
            <h1>Events Explorer</h1>
            <p>Live stream of tracked pageviews, custom interactions, and system events.</p>
        </div>
    </div>

    <!-- Active session filter -->
    <div class="filter-toolbar">
        <div id="activeSessionFilterBox" style="display: none; align-items: center; gap: 6px; background: #e0f2fe; color: #0284c7; padding: 5px 12px; border-radius: var(--radius-md); font-size: 12.5px; font-weight: 600;">
            <span>Session: <span id="activeSessionIdLabel"></span></span>
            <button type="button" id="clearSessionFilterBtn" style="border: none; background: transparent; cursor: pointer; color: #0284c7; font-size: 14px; padding: 0 2px;">✕</button>
        </div>
    </div>
    <!-- Selection overview: key figures and trend for the selected events (or all of them). -->
    <div class="chart-container-card full-width-chart" style="margin-bottom: 24px;">
        <div class="events-selection-head">
            <div class="events-selection-title-wrap">
                <span class="chart-eyebrow">SELECTION</span>
                <h2 class="events-selection-title" id="eventsSelectionTitle">All events</h2>
            </div>
            <button type="button" class="btn-page" id="eventsSelectionReset" hidden>Show all events</button>
        </div>
        <div class="event-insights-strip" id="eventInsightsStrip" aria-label="Event insights" aria-live="polite"></div>
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">EVENT TREND</span>
                <div class="chart-legend-metrics-row" id="eventsTrendLegend"></div>
            </div>
        </div>
        <div class="canvas-wrapper">
            <div id="eventsTrendChart" class="chart-canvas"></div>
            <div id="eventsTrendTooltip" class="chart-tooltip"></div>
        </div>
    </div>

    <!-- Event breakdown: every event of the period; clicking rows selects them. -->
    <div class="data-table-card events-breakdown-card" style="margin-bottom: 24px;">
        <div class="events-card-header">
            <div>
                <span class="events-card-title">Event breakdown</span>
                <p class="events-card-hint">Click an event to focus on it, then click others to compare them.</p>
            </div>
            <input type="search" class="event-series-search events-breakdown-search" id="eventsBreakdownSearch" placeholder="Search events…" autocomplete="off" aria-label="Search events">
        </div>
        <div class="table-responsive">
            <table class="data-table events-breakdown-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th class="num">Count</th>
                        <th class="num">Visitors</th>
                        <th class="share">Share</th>
                        <th class="num">vs previous</th>
                    </tr>
                </thead>
                <tbody id="eventsBreakdownBody">
                    <tr><td colspan="5" class="empty-state">Loading events...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-footer">
            <span id="eventsBreakdownFooter" style="font-size: 13px; color: var(--text-muted);"></span>
            <button type="button" class="btn-page" id="eventsBreakdownMore" hidden>Show more</button>
        </div>
    </div>


    <!-- Events Stream Card with Clean List -->

    <div class="data-table-card events-stream-card">
        <div class="events-card-header">
            <span class="events-card-title" id="eventsStreamTitle">Real-Time Event Stream</span>
            <span id="eventsPageInfo" style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Page 1 of 1</span>
        </div>

        <!-- Clean Event Feed List -->
        <div class="events-list-container" id="eventsListContainer">
            <div class="empty-state" style="padding: 40px; text-align: center; color: var(--text-muted);">Loading events...</div>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-footer">
            <span id="eventsFooterCount" style="font-size: 13px; color: var(--text-muted);">Showing events</span>
            <div class="pagination-controls">
                <button type="button" class="btn-page" id="eventsPrevPage">Previous</button>
                <button type="button" class="btn-page" id="eventsNextPage">Next</button>
            </div>
        </div>
    </div>
</div>
