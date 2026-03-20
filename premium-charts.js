/* Lightweight animated line chart used by dashboard.php */
(function () {
	function safeNumber(v) {
		var n = Number(v);
		return Number.isFinite(n) ? n : 0;
	}

	function fmtPeso(v) {
		return '₱' + safeNumber(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
	}

	function buildSvgLinePath(points) {
		if (!points.length) return '';
		var d = 'M ' + points[0].x + ' ' + points[0].y;
		for (var i = 1; i < points.length; i += 1) {
			d += ' L ' + points[i].x + ' ' + points[i].y;
		}
		return d;
	}

	function AnimatedLineChart(container, opts) {
		if (!container) return;
		opts = opts || {};

		var data = Array.isArray(opts.data) ? opts.data : [];
		var height = safeNumber(opts.height) || 220;
		var width = container.clientWidth || 420;
		var stroke = opts.lineColor || '#3b82f6';
		var showArea = opts.showArea !== false;
		var showPoints = opts.showPoints !== false;

		if (!data.length) {
			container.innerHTML = '<div style="height:' + height + 'px; display:flex; align-items:center; justify-content:center; color:var(--text-light); font-size:0.85rem;">No sales data for the selected period.</div>';
			return;
		}

		var pad = { top: 14, right: 12, bottom: 28, left: 16 };
		var plotW = Math.max(40, width - pad.left - pad.right);
		var plotH = Math.max(40, height - pad.top - pad.bottom);

		var values = data.map(function (d) { return safeNumber(d.value); });
		var maxV = Math.max.apply(null, values);
		var minV = Math.min.apply(null, values);
		if (maxV === minV) {
			maxV += 1;
			minV = 0;
		}

		var points = data.map(function (d, i) {
			var x = pad.left + (i * (plotW / Math.max(1, data.length - 1)));
			var norm = (safeNumber(d.value) - minV) / (maxV - minV);
			var y = pad.top + (plotH - (norm * plotH));
			return { x: Number(x.toFixed(2)), y: Number(y.toFixed(2)), value: safeNumber(d.value), label: String(d.label || '') };
		});

		var linePath = buildSvgLinePath(points);
		var areaPath = linePath + ' L ' + points[points.length - 1].x + ' ' + (pad.top + plotH) + ' L ' + points[0].x + ' ' + (pad.top + plotH) + ' Z';

		var grid = '';
		for (var g = 0; g <= 3; g += 1) {
			var gy = pad.top + (plotH * (g / 3));
			grid += '<line x1="' + pad.left + '" y1="' + gy + '" x2="' + (pad.left + plotW) + '" y2="' + gy + '" stroke="rgba(148,163,184,0.25)" stroke-width="1" />';
		}

		var labels = '';
		points.forEach(function (p) {
			labels += '<text x="' + p.x + '" y="' + (height - 10) + '" text-anchor="middle" font-size="10" fill="rgba(100,116,139,0.95)">' + p.label + '</text>';
		});

		var circles = '';
		if (showPoints) {
			points.forEach(function (p, idx) {
				circles += '<circle cx="' + p.x + '" cy="' + p.y + '" r="3.5" fill="' + stroke + '" stroke="#fff" stroke-width="2">'
					+ '<title>' + p.label + ': ' + fmtPeso(p.value) + '</title></circle>';
				circles += '<circle cx="' + p.x + '" cy="' + p.y + '" r="10" fill="transparent" data-idx="' + idx + '"></circle>';
			});
		}

		var svg = ''
			+ '<svg viewBox="0 0 ' + width + ' ' + height + '" width="100%" height="' + height + '" role="img" aria-label="Sales trend chart">'
			+ '<defs>'
			+ '<linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">'
			+ '<stop offset="0%" stop-color="' + stroke + '" stop-opacity="0.24" />'
			+ '<stop offset="100%" stop-color="' + stroke + '" stop-opacity="0.02" />'
			+ '</linearGradient>'
			+ '</defs>'
			+ grid
			+ (showArea ? '<path d="' + areaPath + '" fill="url(#trendFill)"></path>' : '')
			+ '<path d="' + linePath + '" fill="none" stroke="' + stroke + '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="stroke-dasharray: 2000; stroke-dashoffset: 2000; animation: cpLineDraw 1.2s ease forwards;"></path>'
			+ circles
			+ labels
			+ '</svg>';

		container.innerHTML = svg;

		if (!document.getElementById('cpChartAnimStyle')) {
			var style = document.createElement('style');
			style.id = 'cpChartAnimStyle';
			style.textContent = '@keyframes cpLineDraw { to { stroke-dashoffset: 0; } }';
			document.head.appendChild(style);
		}
	}

	window.AnimatedLineChart = AnimatedLineChart;
})();
