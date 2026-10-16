<!-- Page: Analytics Overview -->
<div class="page-view" id="page-overview">
    <!-- Floating loading banner for slower requests -->
    <div id="overviewLoadingBanner" class="overview-loading-banner" style="display: none;">
        <div class="overview-spinner"></div>
        <span>Loading analytics data...</span>
    </div>

    <div id="overviewDataContainer">
        <aside class="tracking-mode-notice" id="trackingModeNotice" hidden role="status">
            <div class="tracking-mode-notice__icon" aria-hidden="true">i</div>
            <div>
                <strong>Strict privacy mode is active</strong>
                <p>Your generated tracking script uses the default strict mode. It records only minimal, privacy-focused analytics without client-side storage. To enable optional enriched analytics, implement a consent banner before switching modes.</p>
            </div>
        </aside>

        <!-- Active filters (pages, referrers, environment, countries) -->
        <div class="active-filters-bar" id="overviewFilterBar" hidden></div>

        <!-- 1. Core metrics -->
        <div class="umami-stats-grid">
        <!-- Square 1: Visitors -->
        <div class="umami-stat-card">
            <span class="umami-stat-label">Visitors</span>
            <div class="umami-stat-value" id="statVisitors">0</div>
            <div class="umami-stat-delta neutral" id="deltaVisitorsBadge">
                <span class="delta-arrow">–</span>
                <span class="delta-text" id="deltaVisitorsText">0%</span>
            </div>
        </div>

        <!-- Square 2: Visits (Sessions) -->
        <div class="umami-stat-card">
            <span class="umami-stat-label">Visits</span>
            <div class="umami-stat-value" id="statSessions">0</div>
            <div class="umami-stat-delta neutral" id="deltaSessionsBadge">
                <span class="delta-arrow">–</span>
                <span class="delta-text" id="deltaSessionsText">0%</span>
            </div>
        </div>

        <!-- Square 3: Views (Pageviews) -->
        <div class="umami-stat-card">
            <span class="umami-stat-label">Views</span>
            <div class="umami-stat-value" id="statPageviews">0</div>
            <div class="umami-stat-delta neutral" id="deltaPageviewsBadge">
                <span class="delta-arrow">–</span>
                <span class="delta-text" id="deltaPageviewsText">0%</span>
            </div>
        </div>

        <!-- Square 4: Bounce Rate -->
        <div class="umami-stat-card">
            <span class="umami-stat-label">Bounce rate</span>
            <div class="umami-stat-value" id="statBounceRate">0%</div>
            <div class="umami-stat-delta neutral" id="deltaBounceBadge">
                <span class="delta-arrow">–</span>
                <span class="delta-text" id="deltaBounceText">0%</span>
            </div>
        </div>

        <!-- Square 5: Visit Duration -->
        <div class="umami-stat-card">
            <span class="umami-stat-label">Visit duration</span>
            <div class="umami-stat-value" id="statDuration">0s</div>
            <div class="umami-stat-delta neutral" id="deltaDurationBadge">
                <span class="delta-arrow">–</span>
                <span class="delta-text" id="deltaDurationText">0s</span>
            </div>
        </div>

    </div>

    <!-- 2. Full-Width Main Chart Card -->
    <div class="chart-container-card full-width-chart">
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">AUDIENCE & TRAFFIC OVERVIEW</span>
                <div class="chart-legend-metrics-row">
                    <div class="chart-metric-indicator" title="Total Pageviews">
                        <span class="metric-color-dot" style="background: #60a5fa;"></span>
                        <span class="metric-val" id="chartTotalViews">0</span>
                        <span class="metric-name">Views</span>
                    </div>
                    <div class="chart-metric-indicator" title="Unique Visitors">
                        <span class="metric-color-dot" style="background: #a78bfa;"></span>
                        <span class="metric-val" id="chartTotalVisitors">0</span>
                        <span class="metric-name">Visitors</span>
                    </div>
                </div>
            </div>
            <div class="chart-header-controls">
                <div class="chart-metric-toggles" id="chartSeriesToggles" title="Click to show or hide a metric">
                    <button type="button" class="chart-toggle-btn active" data-series="pageviews">
                        <span class="toggle-dot" style="background: #60a5fa;"></span>
                        <span>Views</span>
                    </button>
                    <button type="button" class="chart-toggle-btn active" data-series="visitors">
                        <span class="toggle-dot" style="background: #a78bfa;"></span>
                        <span>Visitors</span>
                    </button>
                </div>
                <div class="chart-metric-toggles" id="chartTypeToggles" title="Choose chart type">
                    <button type="button" class="chart-toggle-btn active" data-chart-type="line">Line</button>
                    <button type="button" class="chart-toggle-btn" data-chart-type="bar">Bars</button>
                </div>
            </div>
        </div>

        <!-- Canvas Container -->
        <div class="canvas-wrapper">
            <canvas id="overviewChart" class="chart-canvas"></canvas>
            <div id="chartTooltip" class="chart-tooltip"></div>
        </div>
    </div>

    <!-- 3. 4 Breakdown Cards (Generous Padding & Spacing) -->
    <div class="overview-breakdowns-grid">
        <!-- Card 1: Top Pages -->
        <div class="data-table-card breakdown-card">
            <div class="breakdown-card-header">
                <div class="breakdown-header-title-group">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="breakdown-header-icon">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    <h3 class="breakdown-card-title">Pages</h3>
                </div>
                <div class="tab-buttons" id="pageTabs">
                    <button type="button" class="chart-toggle-btn active" data-tab="paths">Paths</button>
                    <button type="button" class="chart-toggle-btn" data-tab="titles">Titles</button>
                </div>
            </div>
            <div class="card-column-headers">
                <span>Page</span>
                <span style="text-align: right;">Views</span>
            </div>
            <ul class="clean-pill-list" id="topPagesList">
                <li class="clean-pill-row empty"><span class="pill-muted">No pageviews recorded yet</span></li>
            </ul>
        </div>

        <!-- Card 2: Top Referrers with Favicons -->
        <div class="data-table-card breakdown-card">
            <div class="breakdown-card-header">
                <div class="breakdown-header-title-group">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="breakdown-header-icon">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                    <h3 class="breakdown-card-title">Referrers</h3>
                </div>
                <span class="breakdown-card-sub" id="referrersTotalCount">Direct & Sources</span>
            </div>
            <div class="card-column-headers">
                <span>Source</span>
                <span style="text-align: right;">Views</span>
            </div>
            <ul class="clean-pill-list" id="topReferrersList">
                <li class="clean-pill-row empty"><span class="pill-muted">No referrer data recorded yet</span></li>
            </ul>
        </div>

        <!-- Card 3: Environment (Browsers, OS, Devices) -->
        <div class="data-table-card breakdown-card">
            <div class="breakdown-card-header">
                <div class="breakdown-header-title-group">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="breakdown-header-icon">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <h3 class="breakdown-card-title">Environment</h3>
                </div>
                <div class="tab-buttons" id="envTabs">
                    <button type="button" class="chart-toggle-btn active" data-tab="browsers">Browser</button>
                    <button type="button" class="chart-toggle-btn" data-tab="os">OS</button>
                    <button type="button" class="chart-toggle-btn" data-tab="devices">Device</button>
                </div>
            </div>
            <div class="card-column-headers">
                <span id="envColumnHeader">Browser</span>
                <span style="text-align: right;">Visitors</span>
            </div>
            <ul class="clean-pill-list" id="envList">
                <li class="clean-pill-row empty"><span class="pill-muted">No environment data recorded yet</span></li>
            </ul>
        </div>

        <!-- Card 4: Geography / Countries with Flags -->
        <div class="data-table-card breakdown-card">
            <div class="breakdown-card-header">
                <div class="breakdown-header-title-group">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="breakdown-header-icon">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                    <h3 class="breakdown-card-title">Countries</h3>
                </div>
                <span class="breakdown-card-sub" id="countriesTotalCount">Geography</span>
            </div>
            <div class="card-column-headers">
                <span>Country</span>
                <span style="text-align: right;">Visitors</span>
            </div>
            <ul class="clean-pill-list" id="countriesList">
                <li class="clean-pill-row empty"><span class="pill-muted">No country data recorded yet</span></li>
            </ul>
        </div>
    </div>
    <section class="data-table-card bot-card">
        <div class="breakdown-card-header">
            <div class="breakdown-header-title-group"><h3 class="breakdown-card-title">Blocked bots &amp; internal traffic</h3></div>
            <span id="botCount" class="breakdown-card-sub">0 detections</span>
        </div>
        <div class="tab-buttons bot-tabs" id="botTabs"></div>
        <div class="bot-explorer">
            <div class="bot-chart">
                <div class="canvas-wrapper">
                    <canvas id="botsChart" class="chart-canvas"></canvas>
                    <div id="botsChartTooltip" class="chart-tooltip"></div>
                </div>
                <p class="bot-empty pill-muted" id="botsEmpty" hidden></p>
            </div>
            <ul class="clean-pill-list bot-robots" id="botRobots"></ul>
        </div>
        <div id="botsList"><span class="pill-muted">No detected bot traffic for this period.</span></div>
    </section>
    </div> <!-- /#overviewDataContainer -->

    <!-- Empty / No Traffic State (Inside page-overview) -->
    <div class="no-data" id="no-data-yet" style="display: none;">
        <div class="no-data-icon">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
        </div>
        <h3 class="no-data-title">No data yet</h3>
        <p class="no-data-subtitle">Your analytics dashboard will populate once your site starts receiving traffic.</p>

        <h5 style="margin-top: 16px; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-primary);">If it is not done yet, add the tracking code to your site:</h5>
        
        <div class="code-block-wrapper" style="max-width: 620px; width: 100%; margin: 0 auto 20px auto;">
            <div class="code-block-header">
                <div class="code-block-dots">
                    <span class="code-dot code-dot-red"></span>
                    <span class="code-dot code-dot-yellow"></span>
                    <span class="code-dot code-dot-green"></span>
                    <span class="code-block-title">HTML Tag</span>
                </div>
                <button type="button" class="btn-copy-code" data-copy-target="#overviewSnippetPre" title="Copy tracking tag">
                    <svg class="copy-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <svg class="check-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span class="copy-text">Copy</span>
                </button>
            </div>
            <pre class="code-box" id="overviewSnippetPre"></pre>
        </div>
    </div>
</div>
