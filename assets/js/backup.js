function backup_log(div, msg) {
	// The span for this log entry
	var span = $('<span></span>').html(msg).addClass('newlogrow');

	var curpos=div.scrollTop();
	var bottom = div.prop("scrollHeight");

	var shouldscroll = false;

	// If we're not near the bottom, don't scroll
	if ((bottom-250)-curpos < 20) {
		shouldscroll = true;
	}
	// Now we can add it to the div
	div.append(span);

	if (shouldscroll) {
		// Scroll..
		div.scrollTop(div.prop("scrollHeight"));
	}
}



