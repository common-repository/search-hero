jQuery(document).ready(function ($) {
	function index(userAction){
		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'search_hero_indexing',
				indexAction: userAction,
			},
			dataType: "json",
			success: function (response) {
				results.innerHTML = response.data.message;
				var newProgress = 0;
				if(response.data.count) newProgress = Math.round((response.data.indexed / response.data.count) * 100);
				$('#sh_progress').css('width', newProgress + '%').attr('aria-valuenow', newProgress).text(newProgress + '%');
				$('#percent_ratio1').text(response.data.indexed);
				$('#percent_ratio2').text(response.data.count);
				if(response.data.state == 'continue'){
					index();
				} else if(response.data.state == 'finished') {
					$("#start_indexing").hide();
					$("#continue_indexing").hide();
					$("#start_reindex").hide();
				}
			},
		})
	}
	$("#start_indexing").on("click", function (e) {
		var results = document.getElementById("results")
		e.preventDefault()
		$(this).prop('disabled', true);
		index('index');
	})
	$("#continue_indexing").on("click", function (e) {
		var results = document.getElementById("results")
		e.preventDefault()
		$(this).prop('disabled', true);
		index();
	})
	$("#start_reindex").on("click", function (e) {
		var results = document.getElementById("results")
		e.preventDefault()
		$(this).prop('disabled', true);
		index('reindex');
	})
});
