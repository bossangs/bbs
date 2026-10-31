(function(window, document, $) {
	'use strict';
	function token() { return window.communityCsrfToken || ''; }
	function addToken(form) {
		if (!form || !token() || form.method.toLowerCase() !== 'post') return;
		if (form.querySelector('input[name="_community_csrf"]')) return;
		var input = document.createElement('input');
		input.type = 'hidden'; input.name = '_community_csrf'; input.value = token();
		form.appendChild(input);
	}
	document.addEventListener('submit', function(event) {
		var form = event.target;
		if (form && form.tagName === 'FORM') addToken(form);
	}, true);
	if ($ && $.ajaxPrefilter) {
		$.ajaxPrefilter(function(options, originalOptions) {
			if (!options || String(options.type || options.method || 'GET').toUpperCase() !== 'POST' || !token()) return;
			var data = options.data;
			if (window.FormData && data instanceof window.FormData) {
				if (!data.has('_community_csrf')) data.append('_community_csrf', token());
			} else if (typeof data === 'string') {
				if (data.indexOf('_community_csrf=') < 0) options.data = data + (data ? '&' : '') + '_community_csrf=' + encodeURIComponent(token());
			} else if (data && typeof data === 'object') {
				if (typeof data._community_csrf === 'undefined') data._community_csrf = token();
			} else {
				options.data = '_community_csrf=' + encodeURIComponent(token());
			}
		});
	}
	if ($) {
		$(document).on('submit', 'form.community-ajax', function(event) {
			event.preventDefault();
			var form = $(this);
			var button = form.find('button[type="submit"]').first();
			var original = button.text();
			button.prop('disabled', true).text('处理中…');
			$.xpost(form.attr('action'), form.serialize(), function(code, message) {
				if (code == 0) {
					if ($.alert) $.alert(message); else window.alert(message);
					window.setTimeout(function() { window.location.reload(); }, 500);
				} else {
					if ($.alert) $.alert(message); else window.alert(message);
					button.prop('disabled', false).text(original);
				}
			});
			return false;
		});
	}
})(window, document, window.jQuery);
