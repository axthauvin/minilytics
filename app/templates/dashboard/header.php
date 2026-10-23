<!-- Top Header Component (Minimalist Analytics Header) -->
<header class="top-header">
    <div class="header-left">
        <button type="button" class="btn-sidebar-toggle-header" id="btnHeaderSidebarToggle" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar">
            <svg class="header-toggle-icon" width="18" height="18" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M4 7h16M4 12h16M4 17h16"></path>
            </svg>
        </button>
        <div class="header-title-wrapper">
            <h1 class="header-page-title" id="headerPageTitle">Analytics Overview</h1>
            <div class="live-badge" title="Live visitors active in the last 5 minutes">
                <span class="pulse-dot"></span>
                <span id="liveVisitorsCount">0 live visitors</span>
            </div>
        </div>
    </div>

    <div class="header-actions">
        <!-- Date Range Filter -->
        <div class="range-selector-wrapper">
            <div class="range-selector">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <select id="rangeSelect" aria-label="Select date range">
                    <option value="today">Today</option>
                    <option value="24h">Last 24 Hours</option>
                    <option value="7d" selected>Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                    <option value="90d">Last 90 Days</option>
                    <option value="6m">Last 6 Months</option>
                    <option value="all">All Time</option>
                    <option value="custom">Custom Range...</option>
                </select>
            </div>

            <!-- Custom Date Range Popover -->
            <div class="custom-range-popover" id="customRangePopover" style="display: none;">
                <div class="custom-range-header">
                    <span class="custom-range-title">Custom Date Range</span>
                    <button type="button" class="btn-close-popover" id="btnCloseCustomRange" aria-label="Close">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="custom-range-body">
                    <div class="custom-range-field">
                        <label for="customRangeStart">From</label>
                        <input type="text" id="customRangeStart" class="custom-range-input calendar-input" placeholder="Select a date" readonly />
                    </div>
                    <div class="custom-range-field">
                        <label for="customRangeEnd">To</label>
                        <input type="text" id="customRangeEnd" class="custom-range-input calendar-input" placeholder="Select a date" readonly />
                    </div>
                </div>
                <div class="custom-range-footer">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnCancelCustomRange">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnApplyCustomRange">Apply</button>
                </div>
            </div>
        </div>

        <!-- Refresh Button -->
        <button type="button" class="btn-icon" id="btnRefresh" title="Refresh metrics from database" aria-label="Refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
            </svg>
        </button>
    </div>
</header>
