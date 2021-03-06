jQuery(function ($, undefined) {
	$.fn.extend({
		autocompleteSelMul: function () {
			return this.not('.autocompleteSelMulInited').each(function () {
				var me = $(this).hide().addClass('autocompleteSelMulInited'),
					meCont = me.parent().is('.selectCont') ? me.parent().hide() : me,
					val,
					options,
					keepValue = me.data('keepvalue'),
					input = $('<input type="text" name="' + (keepValue && keepValue.length > 2 ? keepValue : me.attr('id') + '_new') + '" />').insertBefore(meCont),
					list = [],
					cur = [],
					sep = me.data('sep') || ',',
					sepJoin = me.data('sepjoin') || sep + ' ',
					split = function (val) {
						return val.split(new RegExp(sep + '\s*'));
					},
					extractLast = function (term) {
						return split(term).pop();
					},
					writeForm = function () {
						var tmp = split(input.val()),
							val = [],
							others = [];
						$.each(tmp, function () {
							var cur = $.trim(this),
								curOpt = options.filter('[rel="' + cur + '"]');
							if (curOpt.length) {
								val.push(curOpt.attr('value'));
							} else {
								others.push(cur);
							}
						});
						me.val(val);
						if (keepValue) {
							input.val(others.join(sepJoin));
						} else {
							input.attr('disabled', 'disabled');
						}
					},
					reenable = function () {
						input.removeAttr('disabled');
					},
					init = function () {
						options = me.children('option:not([disabled])');
						list = [];
						options.each(function () {
							var opt = $(this),
								txt = $.trim(opt.text());
							opt.attr('rel', txt);
							list.push({
								value: opt.attr('value'),
								label: txt
							});
						});
						val = me.val();
						cur = [];
						if (val && val.length) {
							$.each(val, function (k, v) {
								cur.push(options.filter('[value="' + v + '"]').text());
							});
							cur.push('');
						}
						input.val(cur.join(sepJoin));
					};

				me.removeProp('required').removeAttr('required');

				if (me.attr('placeholder')) {
					input.attr('placeholder', me.attr('placeholder'));
				}

				init();

				input.autocomplete({
					minLength: 1,
					source: function (request, response) {
						// delegate back to autocomplete, but extract the last term
						response($.ui.autocomplete.filter(list, $.trim(extractLast(request.term))));
					},
					focus: function () {
						return false;
					},
					search: function () {
						if ($.ui.version.indexOf('1.12') === 0) {
							// Fix bug in jQuery.ui somewhere where menu.bindings just grows and grows
							// See https://bugs.jqueryui.com/ticket/10050
							$(this).data('ui-autocomplete').menu.bindings = $();
						}
					},
					select: function (event, ui) {
						var terms = split(this.value);
						// remove the current input
						terms.pop();
						// add the selected item
						terms.push(ui.item.label);
						// add placeholder to get the comma-and-space at the end
						terms.push('');
						this.value = terms.join(sepJoin);
						$(this).trigger('change');
						return false;
					}
				});

				me
					.on('autocompleteWriteForm', writeForm)
					.on('autocompleteReenable', reenable)
					.on('autocompleteReinit', init)
					.closest('form').on('submit', function (e) {
						if (!e.isDefaultPrevented()) {
							writeForm();
						}
					});
			}).end();
		},
		autocompleteSel: function () {
			return this.not('.autocompleteSelInited').each(function () {
				var me = $(this).hide().addClass('autocompleteSelInited'),
					meCont = me.parent().is('.selectCont') ? me.parent().hide() : me,
					val,
					options,
					input = $('<input type="text" name="' + me.attr('id') + '_new" ' + (me.attr('required') ? 'required="required"' : '') + '/>').insertBefore(meCont),
					list,
					writeForm = function () {
						var val = input.val(),
							curOpt = options.filter('[rel="' + $.trim(val) + '"]');
						if (curOpt.length) {
							me.val(curOpt.attr('value'));
						}
						input.attr('disabled', 'disabled');
					},
					reenable = function () {
						input.removeAttr('disabled');
					},
					init = function () {
						options = me.children('option:not([disabled])');
						list = [];
						options.each(function () {
							var opt = $(this),
								txt = $.trim(opt.text());
							opt.attr('rel', txt);
							list.push({
								value: opt.attr('value'),
								label: txt
							});
						});
						val = me.val();
						if (val && val.length) {
							input.val(options.filter('[value="' + val + '"]').text());
						} else {
							input.val('');
						}
					};

				me.removeProp('required').removeAttr('required');
				if (me.attr('placeholder')) {
					input.attr('placeholder', me.attr('placeholder'));
				}

				init();

				input.autocomplete({
					minLength: 1,
					source: function (request, response) {
						// delegate back to autocomplete, but extract the last term
						response($.ui.autocomplete.filter(list, $.trim(request.term)));
					},
					focus: function () {
						return false;
					},
					search: function () {
						if ($.ui.version.indexOf('1.12') === 0) {
							// Fix bug in jQuery.ui somewhere where menu.bindings just grows and grows
							// See https://bugs.jqueryui.com/ticket/10050
							$(this).data('ui-autocomplete').menu.bindings = $();
						}
					},
					select: function (event, ui) {
						this.value = ui.item.label;
						$(this).trigger('change');
						me.val(ui.item.value).trigger('change');
						return false;
					}
				});

				me
					.on('autocompleteWriteForm', writeForm)
					.on('autocompleteReenable', reenable)
					.on('autocompleteReinit', init)
					.closest('form').on('submit', function (e) {
						if (!e.isDefaultPrevented()) {
							writeForm();
						}
					});
			}).end();
		}
	});

	$('select.autocompleteSelMul').autocompleteSelMul();
	$('select.autocompleteSel').autocompleteSel();
});
