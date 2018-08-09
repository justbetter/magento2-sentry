define([
	"jquery",
	"jquery/ui"
	"mage/translate",
	"Magento_Ui/js/modal/alert",
], function ($, validation, $t, alert) {
	"use strict";

	$.widget('justbetter.testSentry', {
		options: {
			ajaxUrl: '',
			testSentry: '#sentry_general_sent',
			domainSentry: '#sentry_general_domain'
		},
		_create: function () {
			var self = this;
			self.element.addClass('required-entry');

			$(this.options.testSentry).click(function (e) {
				e.preventDefault();
				if (self.element.val()) {
					self._ajaxSubmit();
				}
			});
		},

		_ajaxSubmit: function () {
			$.ajax({
				url: this.options.ajaxUrl,
				data: {
					domainSentry: $(this.options.domainSentry).val()
				},
				dataType: 'json',
				showLoader: true,
				success: function (result) {
					alert({
						title: result.status ? $t('Success') : $t('Error'),
						content: result.content
					});
				}
			});
		}
	});

	return $.justbetter.testSentry;
});
