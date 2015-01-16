requirejs.config({
	//By default load any module IDs from js/lib
	baseUrl: '/js/',
	paths: {
		vendor: '/vendor',
		jquery: '/vendor/jquery/dist/jquery.min',
		when: '/vendor/when/when',
		autobahn: '/vendor/autobahnjs/package/lib/autobahn',
		livestamp: 'livestamp.min'
	},
	shim: {
		bootstrap: {
			deps: ["jquery"]
		},
		autobahn: {
			deps: ["when"]
		},
		livestamp: {
			deps: ["jquery"]
		}
	}
});

var dependencies = [
	"jquery",
	"bootstrap",
	"netteForms",
	"moment.min",
	"livestamp",
	"nl2br",
	"underscore.string"
	//"when",
	//"autobahn"
];

require(dependencies, function(jquery, bootstrap, netteForms, moment, livestamp, nl2br, _, when, ab) {
		main();
	}
);

var connection;

function main() {

	$.each(q, function(index, f) {
		$(f);
	});

	var $deployToggle = $('#deploy-toggle');

	var host = wamp.host !== null ? wamp.host : window.location.hostname;
	var port = wamp.port !== null ? wamp.port : 8080;
	var connectionString = 'ws://' + host + ':'+ port.toString();

	ab.connect(connectionString,
			function(connection) {
				window.connection = connection;

				connection.subscribe('deploy-start', function(topic, data) {
					var $deploysCount = $deployToggle.find('#deploys-count');
					var count = parseInt($deploysCount.text());
					$deploysCount.text(count + 1);

					var accordionItem = accordionFactory(data.applicationId);
					$('#deploys-accordion').append(accordionItem);

					if(applicationId !== 'undefined') {
						if(applicationId === data.applicationId) {
							$('.btn-deploy').addClass('disabled');
						}
					}
				});

				connection.subscribe('deploy-finish', function(topic, data) {
					var $deploysCount = $deployToggle.find('#deploys-count');
					var count = parseInt($deploysCount.text());
					$deploysCount.text(count - 1);

					$('#accordion-' + data.applicationId).remove();

					if(applicationId !== 'undefined') {
						if(applicationId === data.applicationId) {
							$('.btn-deploy.disabled').button('reset').removeClass('active');
						}
					}
				});

				connection.subscribe('deploy-progress', function(topic, data) {
					var output = $('#output-'+ data.applicationId);
					output.append(nl2br(data.message));
				});

				//private topic to the logged in user
				connection.subscribe(userId, function(topic, data) {

				});

			},

			// WAMP session is gone
			function(code, reason) {
				connection = null;
			}
	);

	$('#commits, #releases, #release').on('click', '.btn-deploy', function(event) {
		var deployData = JSON.parse(this.getAttribute('data-deploy'));
		deployCommit(deployData, this);
		event.preventDefault();
	});

}
;

function deployCommit(data, button)
{
	connection.call("deploy", data).then(function(result) {
		$button = $(button);
		if(result.status === 'error') {
			$button.button('reset');
			$button.button('toggle');
		} else {
			$('.btn-deploy').addClass('disabled');
			$button.button('loading');
		}
	});
}

function accordionFactory(applicationId)
{
	var template = '<div class="accordion-group" id="accordion-%1$s">'
					+	'<div class="accordion-heading">'
					+		'<a class="accordion-toggle" data-toggle="collapse" data-parent="#deploys-accordion" href="#collapse-%1$s">'
					+			'<i class="icon icon-plus"></i> %1$s'
					+		'</a>'
					+	'</div>'
					+	'<div id="collapse-%1$s" class="accordion-body collapse">'
					+		'<div class="accordion-inner well" id="output-%1$s">'
					+		'</div>'
					+	'</div>'
					+'</div>';
	return window._.str.sprintf(template, applicationId);
}