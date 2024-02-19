ms3.panel.UtilitiesGallery = function (config) {
	config = config || {};

	Ext.apply(config, {
		cls: 'container form-with-labels',
		autoHeight: true,
		url: ms3.config.connector_url,
		saveMsg: _('ms3_utilities_gallery_updating'),

		progress: true,
		baseParams: {
			action: 'MiniShop3\\Processors\\Utilities\\Gallery\\Update'
		},
		items: [{
			layout: 'form',
			cls: 'main-wrapper',
			labelWidth: 200,
			labelAlign: 'left',
			border: false,
			buttonAlign: 'left',
			style: 'padding: 0 0 0 7px',
			items: [
				{
					html: String.format(
						_('ms3_utilities_gallery_information'),
						ms3.config.utility_gallery_source_name,
						ms3.config.utility_gallery_source_id,
						ms3.config.utility_gallery_total_products,
						ms3.config.utility_gallery_total_products_files
					),
				},
				{
					xtype: 'fieldset',
					title: _('ms3_utilities_params'),
					id: 'ms3-utilities-gallery-params',
					cls: 'x-fieldset-checkbox-toggle',
					style: 'margin: 5px 0 15px ',
					collapsible: true,
					collapsed: false,
					stateful: true,
					labelAlign: 'top',
					stateEvents: ['collapse', 'expand'],
					items: [
						{
							html: ms3.config.utility_gallery_thumbnails
						},
					]
				},
				{
					name: 'limit',
					xtype: 'numberfield',
					value: 10,
					width: 80,
					fieldLabel: _('ms3_utilities_gallery_for_step')
				},
				{
					name: 'offset',
					xtype: 'numberfield',
					value: 0,
					hidden: true,
				},
				{
					xtype: 'button',
					style: 'margin: 15px 0 0 2px',
					text: '<i class="icon icon-refresh"></i> &nbsp;' + _('ms3_utilities_gallery_refresh'),
					handler: function () {
						var form = this.getForm();
						form.setValues({
							offset: 0
						});
						this.submit(this);
					}, scope: this
				},
				{
					style: 'padding: 15px 0',
					html: '\
						<div id="ms3-utility-gallery-range_outer">\
							<div class="ms3-utility-gallery-labels"><span id="ms3-utility-gallery-label">0%</span><span id="ms3-utility-gallery-iteration"></span></div>\
							<div id="ms3-utility-gallery-progress"><span id="ms3-utility-gallery-progress-bar"></span></div>\
						</div>\
					'
				}
			]
		}],
		listeners: {
			success: {
				fn: function (response) {
					var data = response.result.object;
					var form = this.getForm();
					this.updateProgress(data);

					if (!data.done) {
						form.setValues({
							offset: Number(data.offset)
						});
						this.submit(this);
					}
					else {
						MODx.msg.status({
							title: _('ms3_utilities_gallery_done'),
							message: _('ms3_utilities_gallery_done_message'),
							delay: 5
						});
					}
				}, scope: this
			}
		}
	});
	ms3.panel.UtilitiesGallery.superclass.constructor.call(this, config);
};

Ext.extend(ms3.panel.UtilitiesGallery, MODx.FormPanel, {

	updateProgress: function (data) {
		const progressBlock = document.getElementById('ms3-utility-gallery-range_outer');
		const progressLabel = document.getElementById('ms3-utility-gallery-label');
		const progressBar = document.getElementById('ms3-utility-gallery-progress-bar');
		const progressIteration = document.getElementById('ms3-utility-gallery-iteration');
		progressBlock.style.visibility = 'visible';

		if (data.done) {
			progressLabel.innerHTML = '100%';
			progressBar.style.width = '100%';
			progressIteration.style.visibility = 'hidden';
		} else {
			let progress = (parseFloat((data.offset / data.total) * 100)).toFixed(2);
			progressLabel.innerHTML = progress + '%';
			progressBar.style.width = progress + '%';

			// count iterations
			const totalIterations = Math.ceil(data.total / data.limit);
			const currentIteration = data.offset / data.limit;
			progressIteration.innerHTML = currentIteration + "/" + totalIterations;
		}
	}
});
Ext.reg('ms3-utilities-gallery', ms3.panel.UtilitiesGallery);
