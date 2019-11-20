define([
	"jquery",
	"jquery/ui",
	"mage/translate",
	"Magento_Ui/js/modal/alert"
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

			self.element.click(function (e) {
				e.preventDefault();
				self._ajaxSubmit();
			});
		},

		_ajaxSubmit: function () {
			$.get({
				url: this.options.ajaxUrl,
				dataType: 'json',
				showLoader: true,
				success: function (result) {
					alert({
						title: result.status ? $t('Success') : $t('Error'),
						content: result.content ? result.content : result.message
					});
				}
			});
		}
	});

	return $.justbetter.testSentry;
});
