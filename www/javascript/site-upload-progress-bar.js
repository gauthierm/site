import SiteUploadProgressManager from './site-upload-progress-manager';

// {{{ SiteUploadProgressClient

SiteUploadProgressClient = function(id, status_server, progress_bar)
{
	this.id = id;
	this.progress_bar = progress_bar;
	this.uploaded_files = [];
	this.status_enabled = false;

	SiteUploadProgressManager.onLoad.subscribe(
		function() {
			var manager = SiteUploadProgressManager.getManager();
			manager.setStatusClient(status_server);
		}, this, true);

	this.progress_bar.pulse_step = 0.10;

	this.form = document.getElementById(id);
	this.container = document.getElementById(this.id + '_container');

	YAHOO.util.Event.addListener(this.form, 'submit', this.upload,
		this, true);
};

SiteUploadProgressClient.progress_unknown_text = 'uploading ...';
SiteUploadProgressClient.hours_text = 'hours';
SiteUploadProgressClient.minutes_text = 'minutes';
SiteUploadProgressClient.seconds_left_text = 'seconds left';

SiteUploadProgressClient.prototype.progress = function()
{
	if (this.status_enabled) {
		this.setStatus(1, 0);
	} else {
		this.progress_bar.pulse();
		this.progress_bar.setText(
			SiteUploadProgressClient.progress_unknown_text);
	}
};

SiteUploadProgressClient.prototype.setStatus = function(percent, time)
{
	this.status_enabled = true;
	this.progress_bar.setValueWithAnimation(percent);

	var hours = Math.floor(time / 360);
	var minutes = Math.floor(time / 60) % 60;
	var seconds = time % 60;

	var hours_text = SiteUploadProgressClient.hours_text;
	var minutes_text = SiteUploadProgressClient.minutes_text;
	var seconds_left_text = SiteUploadProgressClient.seconds_left_text;

	var text = '';
	text += (hours > 0) ? hours + ' ' + hours_text + ' ' : '';
	text += (minutes > 0) ? minutes + ' ' + minutes_text + ' ' : '';
	text += seconds + ' ' + seconds_left_text;

	this.progress_bar.setText(text);
};

SiteUploadProgressClient.prototype.upload = function(event)
{
	this.progress_bar.setValue(0);
	this.progress_bar.setText(SiteUploadProgressClient.progress_unknown_text);
	this.showProgressBar();
	SiteUploadProgressManager.getManager().addClient(this);
};

/**
 * Shows the progress bar for this uploader using a smooth animation
 */
SiteUploadProgressClient.prototype.showProgressBar = function()
{
	var animate_div = this.progress_bar.container;
	animate_div.parentNode.style.display = 'block';
	animate_div.parentNode.style.opacity = '0';
	animate_div.parentNode.style.overflow = 'hidden';
	animate_div.parentNode.style.height = '0';
	animate_div.style.visibility = 'hidden';
	animate_div.style.overflow = 'hidden';
	animate_div.style.display = 'block';
	animate_div.style.height = '';
	var height = animate_div.offsetHeight;
	animate_div.style.height = '0';
	animate_div.style.visibility = 'visible';
	animate_div.parentNode.style.height = '';
	animate_div.parentNode.style.overflow = 'visible';

	var slide_animation = new YAHOO.util.Anim(animate_div,
		{ height: { from: 0, to: height } }, 0.5, YAHOO.util.Easing.easeOut);

	var fade_animation = new YAHOO.util.Anim(animate_div.parentNode,
		{ opacity: { from: 0, to: 1 } }, 0.5);

	slide_animation.onComplete.subscribe(fade_animation.animate,
		fade_animation, true);

	slide_animation.animate();
};

SiteUploadProgressClient.prototype.getUploadIdentifier = function()
{
	return document.getElementById(this.id + '_identifier').value;
};

export default SiteUploadProgressClient;
