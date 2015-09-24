RecoApp = Ember.Application.create();

RecoApp.Router.map(function() {
});

RecoApp.IndexRoute = Ember.Route.extend({
	model : function() {
		return Ember.$.ajax({
			url : "api/",
			type : "POST",
			dataType : "json",
			data : {
				action : "recommend"
			},
			beforeSend : startLoadingBar(),
			complete : endLoadingBar()
		});
	},

	setupController : function(controller, model) {
		console.log("Setup Controller for IndexRoute");
		controller.set('movies', model.Recommendations);
		controller.set('clusters', model.Clusters);
		controller.set('crtRating', model.RateChart);
		controller.set('crtQuote', model.QuoteChart);
		controller.set('userid', model.Settings.userid);
		controller.set('max_offers', model.Settings.max_offers);
		controller.set('max_clusters', model.Settings.max_clusters);
	}
});

RecoApp.IndexController = Ember.Controller.extend({
	actions : {
		rateMovie : function(movie, stars) {
			Ember.$.ajax({
				url : "api/",
				type : "POST",
				dataType : "json",
				data : {
					action : "rate",
					offerid : movie.id,
					rate : stars,
					userid : this.get("userid"),
					max_clusters : this.get("max_clusters"),
					max_offers : this.get("max_offers")
				},
				context : this,
				success : function(newModel) {
					this.set('model', newModel);
					this.set('movies', newModel.Recommendations);
					this.set('clusters', newModel.Clusters);
					this.set('crtRating', newModel.RateChart);
					this.set('crtQuote', newModel.QuoteChart);
					console.log("Loading new recommendations  -- successful!");
				},
				beforeSend : startLoadingBar(),
				complete : endLoadingBar()
			});

			console.log("Loading new recommendations -- waiting ...");
		},
		reset : function() {
			console.log("Resetting the session...");
			Ember.$.ajax({
				url : "api/",
				type : "POST",
				dataType : "json",
				data : {
					action : "reset",
					userid : this.get("userid"),
					max_clusters : this.get("max_clusters"),
					max_offers : this.get("max_offers")
				},
				context : this,
				success : function(newModel) {
					this.set('model', newModel);
					this.set('movies', newModel.Recommendations);
					this.set('clusters', newModel.Clusters);
					this.set('crtRating', newModel.RateChart);
					this.set('crtQuote', newModel.QuoteChart);
					console.log("Loading new recommendations  -- successful!");
				},
				beforeSend : startLoadingBar(),
				complete : endLoadingBar()
			});
			console.log("Loading new recommendations -- waiting ...");
		}
	}
});

RecoApp.MovieEntryComponent = Ember.Component.extend({
	actions : {
		rate : function(stars) {
			var movie = this.get("movie");
			this.sendAction("action", movie, stars);
		}
	},
	didInsertElement : function() {
		// update thumbnail
		Ember.run.next(function() {
			Holder.run();
		});
	}
});

Ember.$(document).ajaxSend(function(event, xhr, settings) {
	settings.xhrFields = {
		withCredentials : true
	};
});

function startLoadingBar(direction) {
	if ($("#loadingbar").length === 0)
		$("body").append("<div id='loadingbar'></div>")
	$("#loadingbar").addClass("waiting").append($("<dt/><dd/>"));

	switch (direction) {
	default:
	case '':
	case 'right':
		$("#loadingbar").width((50 + Math.random() * 30) + "%");
		break;
	case 'left':
		$("#loadingbar").addClass("left").animate({
			right : 0,
			left : 100 - (50 + Math.random() * 30) + "%"
		}, 200);
		break;
	case 'down':
		$("#loadingbar").addClass("down").animate({
			left : 0,
			height : (50 + Math.random() * 30) + "%"
		}, 200);
		break;
	case 'up':
		$("#loadingbar").addClass("up").animate({
			left : 0,
			top : 100 - (50 + Math.random() * 30) + "%"
		}, 200);
		break;
	}
}

function endLoadingBar(direction) {
	switch (direction) {
	default:
	case '':
	case 'right':
		$("#loadingbar").width("101%").delay(200).fadeOut(400, function() {
			$(this).remove();
		});
		break;
	case 'left':
		$("#loadingbar").css("left", "0").delay(200).fadeOut(400, function() {
			$(this).remove();
		});
		break;
	case 'down':
		$("#loadingbar").height("101%").delay(200).fadeOut(400, function() {
			$(this).remove();
		});
		break;
	case 'up':
		$("#loadingbar").css("top", "0").delay(200).fadeOut(400, function() {
			$(this).remove();
		});
		break;
	}
}
