jQuery(document).ready(function() {
    jQuery("#youtuber-selection").on("change", function() {
        let src = 'https://www.youtube.com/embed/?listType=playlist&list=' + this.value;
        jQuery("#xrparcade-youtuber").html('<iframe src="' + src + '" width="600" height="340"></iframe>');
    });
});
