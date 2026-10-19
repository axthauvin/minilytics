/**
 * Minilytics Smooth Multi-Series Canvas Area Chart Engine
 * Renders time-series data as a line/area chart or vertical bars.
 * Features:
 * - Smooth Bézier curves for all active series
 * - Cohesive shared Y-axis scaling
 * - Multi-series synchronized crosshair and anchor dots
 * - Rich unified hover tooltip with color badges
 * - Toggleable series visibility
 */

class MinilyticsChart {
    constructor(canvasId, tooltipId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        this.tooltip = document.getElementById(tooltipId);
        if (!this.canvas) return;

        this.ctx = this.canvas.getContext('2d');
        this.data = [];
        this.hoveredIndex = -1;
        this.lockedIndex = null;
        this.chartType = options.chartType === 'bar' ? 'bar' : 'line';
        this.onPointClick = typeof options.onPointClick === 'function'
            ? options.onPointClick
            : null;
        this.onTooltipMetricClick = typeof options.onTooltipMetricClick === 'function'
            ? options.onTooltipMetricClick
            : null;
        this.sortTooltipMetrics = options.sortTooltipMetrics === true;
        this.hideZeroTooltipMetrics = options.hideZeroTooltipMetrics === true;
        this.lockTooltipOnClick = options.lockTooltipOnClick === true;
        this.highlightAnomalies = options.highlightAnomalies === true;
        // Lighter look for small multi-line charts: dashed grid, no dot on the last point.
        this.gridLines = options.gridLines || 3;
        this.gridDash = options.gridDash || [];
        this.markLastPoint = options.markLastPoint !== false;
        // The Y axis goes at least this high, so that a handful of events does not fill the chart.
        this.minScale = options.minScale || 8;

        const defaultSeriesConfig = {
            pageviews: {
                key: 'pageviews',
                label: 'Views',
                singular: 'view',
                color: '#60a5fa', // Light blue
                gradientStart: 'rgba(147, 197, 253, 0.5)',
                gradientEnd: 'rgba(191, 219, 254, 0.06)',
                lineWidth: 3.25
            },
            visitors: {
                key: 'visitors',
                label: 'Visitors',
                singular: 'visitor',
                color: '#a78bfa', // Light violet
                gradientStart: 'rgba(196, 181, 253, 0.42)',
                gradientEnd: 'rgba(221, 214, 254, 0.05)',
                lineWidth: 3
            },
            sessions: {
                key: 'sessions',
                label: 'Visits',
                singular: 'visit',
                color: '#06b6d4', // Cyan
                gradientStart: 'rgba(6, 182, 212, 0.10)',
                gradientEnd: 'rgba(6, 182, 212, 0.00)',
                lineWidth: 2.0
            },
            events: {
                key: 'events',
                label: 'Events',
                singular: 'event',
                color: '#f59e0b', // Amber / Orange
                gradientStart: 'rgba(245, 158, 11, 0.20)',
                gradientEnd: 'rgba(245, 158, 11, 0.00)',
                lineWidth: 2.5
            }
        };

        this.seriesConfig = Object.assign({}, defaultSeriesConfig, options.seriesConfig || {});
        const initialSeries = options.activeSeries || (options.seriesConfig ? Object.keys(options.seriesConfig) : ['pageviews', 'visitors', 'sessions']);
        this.activeSeries = new Set(initialSeries.filter(k => this.seriesConfig[k]));

        this.padding = { top: 25, right: 20, bottom: 32, left: 36 };

        if (this.tooltip && this.onTooltipMetricClick) {
            this.tooltip.classList.add('is-interactive');
            this.tooltip.addEventListener('mouseenter', () => clearTimeout(this.tooltipHideTimer));
            this.tooltip.addEventListener('mouseleave', () => this.hideTooltip());
        }

        this.initEvents();
        window.addEventListener('resize', () => this.resize());
    }

    setData(data, activeSeries = null) {
        this.data = data || [];
        if (activeSeries) {
            this.activeSeries = new Set(Array.isArray(activeSeries) ? activeSeries : [activeSeries]);
        }
        this.hoveredIndex = -1;
        this.lockedIndex = null;
        if (this.tooltip) {
            this.tooltip.classList.remove('visible');
        }
        this.resize();
    }

    toggleSeries(seriesKey) {
        if (!this.seriesConfig[seriesKey]) return;

        // If it is the only active one, don't uncheck (keep at least 1)
        if (this.activeSeries.has(seriesKey) && this.activeSeries.size === 1) {
            return;
        }

        if (this.activeSeries.has(seriesKey)) {
            this.activeSeries.delete(seriesKey);
        } else {
            this.activeSeries.add(seriesKey);
        }

        this.hoveredIndex = -1;
        if (this.tooltip) {
            this.tooltip.classList.remove('visible');
        }
        this.draw();
    }

    isSeriesActive(seriesKey) {
        return this.activeSeries.has(seriesKey);
    }

    setMetric(metric) {
        // Fallback for single metric selection or all
        if (metric === 'all') {
            this.activeSeries = new Set(['pageviews', 'visitors', 'sessions']);
        } else if (this.seriesConfig[metric]) {
            this.activeSeries = new Set([metric]);
        }
        this.draw();
    }

    setChartType(chartType) {
        if (!['line', 'bar'].includes(chartType)) return;
        this.chartType = chartType;
        this.hoveredIndex = -1;
        if (this.tooltip) this.tooltip.classList.remove('visible');
        this.draw();
    }

    resize() {
        if (!this.canvas) return;
        const rect = this.canvas.getBoundingClientRect();
        this.dpr = window.devicePixelRatio || 1;
        
        this.width = rect.width;
        this.height = rect.height;

        this.canvas.width = Math.round(this.width * this.dpr);
        this.canvas.height = Math.round(this.height * this.dpr);

        this.ctx.resetTransform();
        this.ctx.scale(this.dpr, this.dpr);

        this.draw();
    }

    initEvents() {
        if (!this.canvas) return;

        this.canvas.addEventListener('mousemove', (e) => {
            const rect = this.canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            this.handleHover(mouseX, mouseY);
        });

        this.canvas.addEventListener('click', (e) => {
            if (!this.data?.length) return;
            const rect = this.canvas.getBoundingClientRect();
            const index = this.getIndexAtX(e.clientX - rect.left);
            const item = this.data[index];
            if (!item) return;
            if (this.lockTooltipOnClick) this.lockTooltip(index);
            else if (this.onPointClick) this.onPointClick(item, index);
        });

        this.canvas.addEventListener('mouseleave', () => {
            if (this.lockedIndex !== null) return;
            if (this.onTooltipMetricClick) {
                clearTimeout(this.tooltipHideTimer);
                this.tooltipHideTimer = setTimeout(() => this.hideTooltip(), 120);
            } else {
                this.hideTooltip();
            }
        });

    }

    hideTooltip() {
        if (this.lockedIndex !== null) return;
        this.hoveredIndex = -1;
        if (this.tooltip) this.tooltip.classList.remove('visible');
        this.draw();
    }

    lockTooltip(index) {
        if (!this.data[index]) return;
        clearTimeout(this.tooltipHideTimer);
        this.lockedIndex = index;
        this.hoveredIndex = index;
        this.draw();
        this.updateTooltip(index);
    }

    unlockTooltip() {
        this.lockedIndex = null;
        this.hoveredIndex = -1;
        if (this.tooltip) this.tooltip.classList.remove('visible');
        this.draw();
    }

    isAnomalousPoint(seriesKey, value, index) {
        if (!this.highlightAnomalies || value < 5) return false;
        // Compare against the preceding week, including zero days. This makes
        // a jump such as 0 → 80 visible even for a frequently used event.
        const values = this.data.slice(Math.max(0, index - 7), index)
            .map((point) => Number(point[seriesKey]) || 0)
            .sort((left, right) => left - right);
        if (values.length < 4) return false;
        const middle = Math.floor(values.length / 2);
        const median = values.length % 2 ? values[middle] : (values[middle - 1] + values[middle]) / 2;
        if (median === 0) return value >= 5;
        return value >= median * 3;
    }

    handleHover(mouseX, mouseY) {
        if (!this.data || this.data.length === 0) return;
        if (this.lockedIndex !== null) return;

        const index = this.getIndexAtX(mouseX);

        this.hoveredIndex = index;
        this.draw();
        this.updateTooltip(index);
    }

    getIndexAtX(mouseX) {
        const plotWidth = this.width - this.padding.left - this.padding.right;
        const relativeX = mouseX - this.padding.left;
        const rawIndex = this.chartType === 'bar'
            ? Math.floor(relativeX / (plotWidth / this.data.length))
            : Math.round(relativeX / (plotWidth / Math.max(1, this.data.length - 1)));
        return Math.max(0, Math.min(this.data.length - 1, rawIndex));
    }

    updateTooltip(index) {
        if (!this.tooltip || index < 0 || !this.data[index]) return;
        const item = this.data[index];
        const dateStr = item.full_label || item.label || '';

        // Build list of metrics for all active series
        const seriesKeys = Object.keys(this.seriesConfig).filter(k => this.activeSeries.has(k));
        if (this.sortTooltipMetrics) {
            seriesKeys.sort((left, right) => {
                const difference = (Number(item[right]) || 0) - (Number(item[left]) || 0);
                return difference || this.seriesConfig[left].label.localeCompare(this.seriesConfig[right].label);
            });
        }
        
        const visibleSeriesKeys = this.hideZeroTooltipMetrics
            ? seriesKeys.filter(key => (Number(item[key]) || 0) > 0)
            : seriesKeys;
        const metricsHtml = visibleSeriesKeys.map(key => {
            const cfg = this.seriesConfig[key];
            const val = Number(item[key]) || 0;
            const tag = this.onTooltipMetricClick ? 'button' : 'div';
            const isAnomaly = this.isAnomalousPoint(key, val, index);
            return `
                <${tag} class="chart-tooltip-metric${this.onTooltipMetricClick ? ' is-actionable' : ''}" ${this.onTooltipMetricClick ? `data-tooltip-series="${key}" type="button"` : ''}>
                    <span class="chart-tooltip-dot" style="background: ${cfg.color};"></span>
                    <span class="chart-tooltip-name">${cfg.label}</span>
                    ${isAnomaly ? '<span class="chart-tooltip-anomaly" title="Unusual peak: at least 3× the median for this event">!</span>' : ''}
                    <span class="chart-tooltip-val">${val.toLocaleString()}</span>
                </${tag}>
            `;
        }).join('');
        const isLocked = this.lockedIndex === index;

        this.tooltip.innerHTML = `
            <div class="chart-tooltip-date">${dateStr}${isLocked ? '<span class="chart-tooltip-pinned">Pinned</span>' : ''}</div>
            <div class="chart-tooltip-metrics-list">${metricsHtml}</div>
            ${isLocked && this.onPointClick ? '<div class="chart-tooltip-actions"><button type="button" data-tooltip-action="sessions">View sessions for this day</button><button type="button" class="chart-tooltip-unpin" data-tooltip-action="unpin" aria-label="Unpin tooltip">×</button></div>' : (this.onTooltipMetricClick ? '<div class="chart-tooltip-hint">Click the graph to pin this list, then select an event</div>' : (this.onPointClick ? '<div class="chart-tooltip-hint">Click to view sessions</div>' : ''))}
        `;
        if (this.onTooltipMetricClick) {
            this.tooltip.querySelectorAll('[data-tooltip-series]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.onTooltipMetricClick(item, button.dataset.tooltipSeries, index);
                });
            });
        }
        this.tooltip.querySelector('[data-tooltip-action="sessions"]')?.addEventListener('click', () => this.onPointClick(item, index));
        this.tooltip.querySelector('[data-tooltip-action="unpin"]')?.addEventListener('click', () => this.unlockTooltip());

        const plotY = this.padding.top;
        const plotWidth = this.width - this.padding.left - this.padding.right;
        const plotHeight = this.height - this.padding.top - this.padding.bottom;
        const stepX = plotWidth / Math.max(1, this.data.length - 1);
        const pointX = this.chartType === 'bar'
            ? this.padding.left + (index + 0.5) * (plotWidth / this.data.length)
            : this.padding.left + index * stepX;

        // Find max value across all active series to place tooltip
        const allActiveValues = [];
        this.activeSeries.forEach(k => {
            this.data.forEach(d => allActiveValues.push(Number(d[k]) || 0));
        });
        let maxVal = Math.max(...allActiveValues, this.minScale);
        const roundStep = maxVal > 100 ? 50 : (maxVal > 50 ? 20 : (maxVal > 20 ? 10 : 4));
        maxVal = Math.ceil(maxVal / roundStep) * roundStep;

        // Use highest point among active series at this index
        const currentIndexValues = seriesKeys.map(k => Number(item[k]) || 0);
        const topValAtIndex = Math.max(...currentIndexValues, 0);
        const pointY = plotY + plotHeight - (topValAtIndex / maxVal) * plotHeight;

        // Keep the tooltip beside the selected point. The left-side fallback
        // prevents it from being clipped for the final points in the chart.
        const gap = 14;
        const tooltipWidth = this.tooltip.offsetWidth;
        const tooltipHeight = this.tooltip.offsetHeight;
        const canOpenRight = pointX + gap + tooltipWidth <= this.width - 4;
        const left = canOpenRight
            ? pointX + gap
            : Math.max(4, pointX - gap - tooltipWidth);
        const top = Math.max(4, Math.min(pointY - tooltipHeight / 2, this.height - tooltipHeight - 4));
        this.tooltip.style.left = `${left}px`;
        this.tooltip.style.top = `${top}px`;
        this.tooltip.style.transform = 'none';

        this.tooltip.classList.add('visible');
    }

    draw() {
        if (!this.ctx || !this.width || !this.height) return;
        const ctx = this.ctx;
        ctx.clearRect(0, 0, this.width, this.height);

        if (!this.data || this.data.length === 0) {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('No data available for this period', this.width / 2, this.height / 2);
            return;
        }

        const plotX = this.padding.left;
        const plotY = this.padding.top;
        const plotWidth = this.width - this.padding.left - this.padding.right;
        const plotHeight = this.height - this.padding.top - this.padding.bottom;

        // 1. Determine common Y-axis maximum across all active series
        const allActiveValues = [];
        this.activeSeries.forEach(k => {
            this.data.forEach(d => allActiveValues.push(Number(d[k]) || 0));
        });

        let maxVal = Math.max(...allActiveValues, this.minScale);
        const roundStep = maxVal > 100 ? 50 : (maxVal > 50 ? 20 : (maxVal > 20 ? 10 : 4));
        maxVal = Math.ceil(maxVal / roundStep) * roundStep;

        const getY = (val) => {
            return plotY + plotHeight - (val / maxVal) * plotHeight;
        };

        const stepX = plotWidth / Math.max(1, this.data.length - 1);

        // 2. Subtle horizontal grid lines & Y-axis numbers
        const gridLines = this.gridLines;
        ctx.save();
        ctx.setLineDash(this.gridDash);
        ctx.strokeStyle = this.gridDash.length ? '#e2e8f0' : '#f1f5f9';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#94a3b8';
        ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';

        for (let i = 0; i <= gridLines; i++) {
            const val = Math.round((maxVal / gridLines) * i);
            const y = getY(val);

            ctx.beginPath();
            ctx.moveTo(plotX, y);
            ctx.lineTo(plotX + plotWidth, y);
            ctx.stroke();

            ctx.fillText(val.toLocaleString(), plotX - 8, y);
        }
        ctx.restore();

        // 3. Draw dashed vertical crosshair if hovering
        const activeIdx = this.hoveredIndex >= 0 ? this.hoveredIndex : (this.markLastPoint && this.data.length > 3 ? this.data.length - 1 : -1);
        if (activeIdx >= 0 && activeIdx < this.data.length && this.hoveredIndex >= 0) {
            const crosshairX = this.chartType === 'bar'
                ? plotX + (activeIdx + 0.5) * (plotWidth / this.data.length)
                : plotX + activeIdx * stepX;
            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([3, 3]);
            ctx.strokeStyle = '#94a3b8';
            ctx.lineWidth = 1;
            ctx.moveTo(crosshairX, plotY);
            ctx.lineTo(crosshairX, plotY + plotHeight);
            ctx.stroke();
            ctx.restore();
        }

        // 4. Render each active series (in background-to-foreground order)
        const preferredOrder = ['pageviews', 'events', 'sessions', 'visitors'];
        const renderOrder = preferredOrder.filter(k => this.activeSeries.has(k) && this.seriesConfig[k]);
        Object.keys(this.seriesConfig).forEach(k => {
            if (this.activeSeries.has(k) && !renderOrder.includes(k)) renderOrder.push(k);
        });

        if (this.chartType === 'bar') {
            const slotWidth = plotWidth / this.data.length;
            // Metrics share a single slot. Since visitors are a subset of views,
            // drawing them last makes the smaller visitor bar visible in front.
            const barWidth = Math.max(1, Math.min(slotWidth * 0.72, 96));

            renderOrder.forEach((key) => {
                const cfg = this.seriesConfig[key];
                this.data.forEach((item, index) => {
                    const value = Number(item[key]) || 0;
                    const x = plotX + index * slotWidth + (slotWidth - barWidth) / 2;
                    const y = getY(value);
                    const height = Math.max(0, plotY + plotHeight - y);
                    if (height === 0) return;
                    ctx.fillStyle = cfg.color;
                    ctx.beginPath();
                    ctx.roundRect(x, y, barWidth, height, [3, 3, 0, 0]);
                    ctx.fill();
                });
            });
        } else renderOrder.forEach(key => {
            const cfg = this.seriesConfig[key];
            const points = this.data.map((item, i) => ({
                x: plotX + i * stepX,
                y: getY(Number(item[key]) || 0),
                val: Number(item[key]) || 0
            }));

            if (points.length <= 1) return;

            // A. Smooth Area Gradient Fill
            const gradient = ctx.createLinearGradient(0, plotY, 0, plotY + plotHeight);
            gradient.addColorStop(0, cfg.gradientStart);
            gradient.addColorStop(0.7, cfg.gradientEnd);
            gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');

            ctx.beginPath();
            ctx.moveTo(points[0].x, plotY + plotHeight);
            ctx.lineTo(points[0].x, points[0].y);

            const floorY = plotY + plotHeight;
            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[i === 0 ? 0 : i - 1];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[i + 2] || p2;

                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = Math.min(p1.y + (p2.y - p0.y) / 6, floorY);
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = Math.min(p2.y - (p3.y - p1.y) / 6, floorY);

                ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
            }

            ctx.lineTo(points[points.length - 1].x, plotY + plotHeight);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();

            // B. Smooth Bézier Curve Stroke
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);

            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[i === 0 ? 0 : i - 1];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[i + 2] || p2;

                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = Math.min(p1.y + (p2.y - p0.y) / 6, floorY);
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = Math.min(p2.y - (p3.y - p1.y) / 6, floorY);

                ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
            }

            ctx.strokeStyle = cfg.color;
            ctx.lineWidth = cfg.lineWidth;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();

            // C. Anchor Dot on active point for this series
            if (activeIdx >= 0 && activeIdx < points.length) {
                const pt = points[activeIdx];
                ctx.beginPath();
                ctx.arc(pt.x, pt.y, 4.5, 0, Math.PI * 2);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                ctx.lineWidth = 2.5;
                ctx.strokeStyle = cfg.color;
                ctx.stroke();
            }
        });

        // 5. X-axis Labels (Dates / Time steps)
        ctx.fillStyle = '#94a3b8';
        ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        const maxLabels = this.width < 420 ? 3 : (this.width < 640 ? 5 : 7);
        const labelInterval = Math.max(1, Math.floor(this.data.length / maxLabels));
        for (let i = 0; i < this.data.length; i += labelInterval) {
            const ptX = this.chartType === 'bar'
                ? plotX + (i + 0.5) * (plotWidth / this.data.length)
                : plotX + i * stepX;
            const label = this.data[i].label || '';
            // Keep the first and last labels inside the canvas.
            const half = ctx.measureText(label).width / 2;
            ctx.fillText(label, Math.min(Math.max(ptX, half + 2), this.width - half - 2), plotY + plotHeight + 10);
        }
    }

}

window.MinilyticsChart = MinilyticsChart;
