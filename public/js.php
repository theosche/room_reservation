<?php 
namespace Theosche\RoomReservation;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
?>
function addOccurrence() {
	if (occurrencePrice.length >= 10) {
		alert("Maximum 10 occurrences par formulaire");
		return;
	}
	const container = document.getElementById('occurrences');
	const newOccurrence = document.createElement('div');
	newOccurrence.classList.add('date-time-group','occurrence');
	newOccurrence.innerHTML = `
			<span class="remove-btn" onclick="removeOccurrence(this)">✖</span>
			<input type="date" value="" aria-label="Date" name="reservation_date[]" class="reservationDate" required>
			<input type="time" value="" aria-label="Heure de début" name="start_time[]" class="startTime" required>
			<input type="time" value="" aria-label="Heure de fin" name="end_time[]" class="endTime" required>
			<span class="availability-status"></span>
			<?php if (defined_local("OPTIONS")):?>
				<div class="break"></div>
				<?php foreach(OPTIONS as $opt): ?>
					<label class="opt-label">
					<input type="checkbox" checked class="opt-hidden opt-<?= $opt['dbkey'] ?>" name="<?= $opt['dbkey'] ?>[]" value="off">
					<input type="checkbox" class="opt opt-<?= $opt['dbkey'] ?>" name="<?= $opt['dbkey'] ?>[]" value="on">
					<?= $opt['text'] ?> (+ <?= $opt['price'] ?>.-)</label>
				<?php endforeach; ?>
			<?php endif;?>
			<div class="break"></div>
			<p class="tarif">Tarif : 0.-</p>
	`;
	container.appendChild(newOccurrence);
	occurrencePrice.push(false);
}

function removeOccurrence(element) {
	const parent = element.parentNode;
	const index = Array.from(document.querySelectorAll('.occurrence')).indexOf(parent);
	parent.remove();
	occurrencePrice.splice(index,1);
	updateTotalCost();
}

function checkAvailability(startDateTime, endDateTime, self_uid=null) {
	let conflict;
	if (self_uid) {
		conflict = eventsCache.some(event => {
			return (
				(startDateTime < event[1] && endDateTime > event[0] && event.uid != self_uid) // Chevauchement
			);
		});
	} else {
		conflict = eventsCache.some(event => {
			return (
				(startDateTime < event[1] && endDateTime > event[0]) // Chevauchement
			);
		});
	}
	return !conflict; // Retourne true si disponible, false sinon
}

function updateTotalCost() {
	let grossTotal = 0;
	occurrencePrice.forEach(price => {
		if (price) grossTotal += price;
	});
	let total = grossTotal;
	document.getElementById('total-cost').textContent = grossTotal;
	<?php if(defined_local("ENTITYTYPES")):?>
		const entitytypes = ["<?= implode('","',array_column(ENTITYTYPES, 'dbkey')) ?>"];
		const entitychecked = entitytypes.map((x) => document.getElementById(x).checked);
		const entityprices = [<?= implode(',',array_column(ENTITYTYPES, 'price')) ?>];
	<?php else:?>
		const entitytypes = [];
		const entitychecked = [];
		const entityprices = [];
	<?php endif;?>
	const don_html = document.getElementById('don');
	const don = don_html ? Number(don_html.value) : 0;
	const special_red_html = document.getElementById('special_red');
	const special_red = special_red_html ? Number(special_red_html.value) : 0;
	if (entitychecked.some(x => Boolean) || don || special_red) {
		document.getElementById('total-cost-p').style.display = 'block';
	} else {
		document.getElementById('total-cost-p').style.display = 'none';
	}
	entitytypes.forEach(function(item,index) {
		if (entitychecked[index]) {
			document.getElementById(item + "-text").style.display = 'block';
			document.getElementById(item + "-price").textContent = (entityprices[index] > 0 ? "+" : "") + entityprices[index]*grossTotal;
			total += entityprices[index]*grossTotal;
		} else {
			document.getElementById(item + "-text").style.display = 'none';
		}
	})
	<?php if (USE_DONATION):?>
	if (don && don>0) {
		document.getElementById('don-cost-p').style.display = 'block';
		document.getElementById('don-cost').textContent = don;
		total += don;
	} else {
		document.getElementById('don-cost-p').style.display = 'none';
	}
	<?php endif;?>
	<?php if (USE_SPECIAL_RED):?>
	if (special_red) {
		document.getElementById('special-red-cost-p').style.display = 'block';
		document.getElementById('special-red-cost').textContent = -Math.abs(special_red);
		total -= Math.abs(special_red);
	} else if (special_red_html) {
		document.getElementById('special-red-cost-p').style.display = 'none';
	}
	<?php endif;?>
	document.getElementById('final-cost').textContent = total;
}

function updateRow(row) {
	const date = row.querySelector('.reservationDate').value;
	const startTime = row.querySelector('.startTime').value;
	const endTime = row.querySelector('.endTime').value;
	const index = Array.from(document.querySelectorAll('.occurrence')).indexOf(row);
	const status = row.querySelector('.availability-status');
	let text='';
	// Vérifiez si tous les champs nécessaires sont remplis
	if (date && startTime && endTime) {
		if (endTime <= startTime) {
			status.textContent = 'Invalide';
			status.classList.remove('span-valid');
			status.classList.add('span-invalid');
			occurrencePrice[index] = false;
		} else {
			const startDateTime = new Date(`${date}T${startTime}`);
			let d = new Date();
			if (startDateTime < d) {
				status.textContent = 'Passé';
				status.classList.remove('span-valid');
				status.classList.add('span-invalid');
				occurrencePrice[index] = false;
			} else if (d.setMonth(d.getMonth() + <?= FUTURE_RESERVATION_LIMIT ?>) < startDateTime) {
				status.textContent = 'trop loin';
				status.classList.remove('span-valid');
				status.classList.add('span-invalid');
				occurrencePrice[index] = false;
			} else {
				const endDateTime = new Date(`${date}T${endTime}`);
				let isAvailable;
				if (review) {
					const self_uid = row.getAttribute('data-uid');
					isAvailable = checkAvailability(startDateTime, endDateTime, self_uid);
				} else {
					isAvailable = checkAvailability(startDateTime, endDateTime);
				}
				
				if (isAvailable) {
					status.textContent = 'Disponible';
					status.classList.add('span-valid');
					status.classList.remove('span-invalid');
					// Compute pricing
					const duration = (endDateTime - startDateTime) / (1000 * 60 * 60);
					let price = 0;
					if (duration <= <?=MAX_HOURS_SHORT?> || startTime >= (<?=ALWAYS_SHORT_AFTER?> + ':00')) {
						text = "Réservation courte durée (< <?=MAX_HOURS_SHORT?>h) ou soirée (dès <?=ALWAYS_SHORT_AFTER?>h)";
						price = <?=PRICE_SHORT?>;
					} else {
						price = <?=PRICE_FULL_DAY?>;
						text = 'Réservation journée entière';
					}
					<?php if(defined_local("OPTIONS")):?>
						const options = ["<?= implode('","',array_column(OPTIONS, 'dbkey')) ?>"];
						const optionschecked = options.map(function(x) {
							const checked = row.querySelector('.opt.opt-' + x).checked;
							row.querySelector('.opt-hidden.opt-' + x).checked = !checked;
							return(checked);
						});
						const optionsprices = [<?= implode(',',array_column(OPTIONS, 'price')) ?>];
						const optionsstr = ["<?= implode('","',array_column(OPTIONS, 'text_short')) ?>"];
					<?php else:?>
						const options = [];
						const optionschecked = [];
						const optionsprices = [];
						const optionsstr = [];
					<?php endif;?>
					let enabled_str = [];
					options.forEach(function(item,index) {
						if (optionschecked[index]) {
							price += optionsprices[index];
							enabled_str.push(optionsstr[index]); 
						}
					});
					if (enabled_str.length == 1) {
						text += " avec " + enabled_str[0];
					} else if (enabled_str.length > 1) {
						text += " avec ";
						for (let i=0; i < enabled_str.length-1; i++) {
							text += enabled_str[i] + ", ";
						}
						text = text.substring(0, text.length - 2); // remove last ','
						text += " et " + enabled_str[enabled_str.length-1];
					}
					occurrencePrice[index] = price;
				} else {
					status.textContent = 'Indisponible';
					status.classList.remove('span-valid');
					status.classList.add('span-invalid');
					occurrencePrice[index] = false;
				}
			}
		}
	} else {
		status.classList.remove('span-valid');
		status.classList.remove('span-invalid');
		occurrencePrice[index] = false;
	}
	const tarifP = row.querySelector('.tarif');
	if (occurrencePrice[index]) {
		tarifP.style.display = 'block';
		tarifP.textContent = text + ': ' + occurrencePrice[index] + '.-';
	} else {
		tarifP.style.display = 'none';
	}
}

document.addEventListener('input', (event) => {
	const target = event.target;
	if (target.matches('.occurrence input')) {
		const row = target.closest('.occurrence');
		updateRow(row);
		updateTotalCost();
	<?php
		if (defined_local("ENTITYTYPES")) {
			$entityIDstring = '#' . implode(',#',array_column(ENTITYTYPES, 'dbkey')) . ',';
		} else {
			$entityIDstring = '';
		}
	?>
	} else if (target.matches('<?=$entityIDstring?>#don,#special_red')) {
		updateTotalCost();
	}
});