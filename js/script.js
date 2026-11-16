/**
 * Reseller Map — front-end behaviour.
 * Built on jQuery on purpose: WordPress core, Porto and WPBakery all load
 * jQuery on the front end anyway, so this avoids a second DOM library and
 * keeps the code consistent with the rest of the stack.
 */
(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : value).html();
	}

	function formatDistance(km) {
		if (km === null || km === undefined || isNaN(km)) {
			return '';
		}
		if (km < 1) {
			return Math.round(km * 1000) + ' m';
		}
		return km.toFixed(1).replace('.', ',') + ' km';
	}

	function buildResultItem(location) {
		var $icon = $('<i>', {
			class: 'fas fa-map-marker-alt gieffeplus-result-icon',
			'aria-hidden': 'true',
		});

		var $link = $('<a>', {
			href: location.link || '#',
			target: '_blank',
			rel: 'noopener noreferrer',
			text: location.title || '',
		});

		var $address = $('<span>', {
			class: 'gieffeplus-result-address',
			text: location.address || '',
		});

		var $content = $('<div>', { class: 'gieffeplus-result-content' }).append($link, $address);
		var $item = $('<li>', { class: 'gieffeplus-result-item' }).append($icon, $content);

		var distanceLabel = formatDistance(location.distance);
		if (distanceLabel) {
			$item.append($('<span>', { class: 'gieffeplus-result-distance', text: distanceLabel }));
		}

		return $item;
	}

	function initInstance($root) {
		var $map = $root.find('[data-role="map"]');
		var $input = $root.find('[data-role="address"]');
		var $form = $root.find('[data-role="form"]');
		var $list = $root.find('[data-role="results"]');
		var $status = $root.find('[data-role="status"]');
		var $geoBtn = $root.find('[data-role="geolocate"]');

		if (!$map.length || !$input.length || !$form.length || !$list.length) {
			return;
		}

		var zoom = parseInt($root.data('zoom'), 10) || 10;
		var centerLat = parseFloat($root.data('center-lat'));
		var centerLng = parseFloat($root.data('center-lng'));
		var resultsCount = parseInt($root.data('results'), 10) || 6;

		var msgSearching = $root.data('msg-searching') || 'Ricerca in corso…';
		var msgNotFound = $root.data('msg-not-found') || 'Indirizzo non trovato.';
		var msgError = $root.data('msg-error') || "Si è verificato un errore. Riprova più tardi.";
		var msgEmpty = $root.data('msg-empty') || 'Nessun rivenditore trovato nelle vicinanze.';
		var msgLocating = $root.data('msg-locating') || 'Rilevamento della posizione…';
		var msgGeoError = $root.data('msg-geo-error') || 'Impossibile rilevare la tua posizione.';
		var msgGeoDenied = $root.data('msg-geo-denied') || 'Permesso di geolocalizzazione negato.';

		var map = new google.maps.Map($map.get(0), {
			zoom: zoom,
			center: { lat: centerLat, lng: centerLng },
			mapTypeId: google.maps.MapTypeId.ROADMAP,
		});

		var autocomplete = new google.maps.places.Autocomplete($input.get(0), {
			fields: ['geometry'],
		});
		var geocoder = new google.maps.Geocoder();
		var infoWindow = new google.maps.InfoWindow();
		var markers = [];

		function clearMarkers() {
			markers.forEach(function (marker) {
				google.maps.event.clearInstanceListeners(marker);
				marker.setMap(null);
			});
			markers = [];
		}

		function setStatus(message, isError) {
			$status.text(message || '').toggleClass('is-error', !!isError);
		}

		function renderResults(originLat, originLng, data) {
			clearMarkers();
			$list.empty();

			if (!Array.isArray(data) || data.length === 0) {
				setStatus(msgEmpty, false);
				return;
			}

			setStatus('', false);

			var bounds = new google.maps.LatLngBounds();
			var $items = $();

			data.forEach(function (item) {
				var position = { lat: parseFloat(item.lat), lng: parseFloat(item.lng) };
				if (isNaN(position.lat) || isNaN(position.lng)) {
					return;
				}
				bounds.extend(position);

				var marker = new google.maps.Marker({
					position: position,
					map: map,
					title: item.title || '',
				});

				marker.addListener('click', function () {
					infoWindow.setContent(
						'<strong>' + escapeHtml(item.title) + '</strong><br>' + escapeHtml(item.address)
					);
					infoWindow.open(map, marker);
				});

				markers.push(marker);
				$items = $items.add(buildResultItem(item));
			});

			$list.append($items);
			bounds.extend({ lat: originLat, lng: originLng });
			map.fitBounds(bounds);
		}

		function fetchNearby(lat, lng) {
			$.ajax({
				url: window.gieffeplusSettings.restUrl,
				method: 'GET',
				dataType: 'json',
				data: { lat: lat, lng: lng, limit: resultsCount },
			})
				.done(function (data) {
					renderResults(lat, lng, data);
				})
				.fail(function () {
					setStatus(msgError, true);
				})
				.always(function () {
					$form.removeClass('is-loading');
				});
		}

		function search(address) {
			if (!address) {
				return;
			}

			$form.addClass('is-loading');
			setStatus(msgSearching, false);

			geocoder.geocode({ address: address }, function (results, geoStatus) {
				if (geoStatus !== google.maps.GeocoderStatus.OK || !results || !results.length) {
					$form.removeClass('is-loading');
					setStatus(msgNotFound, true);
					return;
				}

				var location = results[0].geometry.location;
				fetchNearby(location.lat(), location.lng());
			});
		}

		function locateMe() {
			$form.addClass('is-loading');
			setStatus(msgLocating, false);

			navigator.geolocation.getCurrentPosition(
				function (position) {
					var lat = position.coords.latitude;
					var lng = position.coords.longitude;

					// Best-effort: fill the input with a human-readable address,
					// but don't block the search on it.
					geocoder.geocode({ location: { lat: lat, lng: lng } }, function (results, geoStatus) {
						if (geoStatus === google.maps.GeocoderStatus.OK && results && results.length) {
							$input.val(results[0].formatted_address);
						}
					});

					fetchNearby(lat, lng);
				},
				function (error) {
					$form.removeClass('is-loading');
					setStatus(error.code === error.PERMISSION_DENIED ? msgGeoDenied : msgGeoError, true);
				},
				{ enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
			);
		}

		if ($geoBtn.length) {
			if ('geolocation' in navigator) {
				$geoBtn.on('click', function (event) {
					event.preventDefault();
					locateMe();
				});
			} else {
				$geoBtn.hide();
			}
		}

		$form.on('submit', function (event) {
			event.preventDefault();
			search($.trim($input.val()));
		});
	}

	window.gieffeplusInitMap = function () {
		$('.gieffeplus-reseller-map').each(function () {
			var $root = $(this);
			if ($root.data('initialized')) {
				return;
			}
			$root.data('initialized', true);
			initInstance($root);
		});
	};
})(jQuery);
