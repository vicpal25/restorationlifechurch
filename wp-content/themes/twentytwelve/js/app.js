
jQuery( document ).ready(function() {

  // jQuery(document).foundation();

  jQuery('#nav').localScroll(800);
	//.parallax(xPosition, speedFactor, outerHeight) options:
	//xPosition - Horizontal position of the element
	//inertia - speed to move relative to vertical scroll. Example: 0.1 is one tenth the speed of scrolling, 2 is twice the speed of scrolling
	//outerHeight (true/false) - Whether or not jQuery should use it's outerHeight option to determine when a section is in the viewport
	jQuery('.verses').parallax("50%", 0.3);
	jQuery('.sky').parallax("50%", 0.1);
	jQuery('.bg').parallax("50%", 0.4);
	jQuery('#third').parallax("50%", 0.3);

});
