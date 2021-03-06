import { v4 as uuid } from 'uuid';
import { biddersDelay } from './bidders/bidders-delay';
import { billTheLizardConfigurator } from './ml/configuration';
import { featuredVideoAutoPlayDisabled } from './ml/executor';
import {
	AdSlot,
	bidders,
	billTheLizard,
	btRec,
	confiant,
	context,
	durationMedia,
	events,
	eventService,
	InstantConfigCacheStorage,
	JWPlayerManager,
	krux,
	moatYi,
	moatYiEvents,
	permutive,
	nielsen,
	SlotTweaker,
	taxonomyService,
	utils
} from '@wikia/ad-engine';
import { babDetection } from './wad/bab-detection';
import ads from './setup';
import pageTracker from './tracking/page-tracker';
import slots from './slots';
import videoTracker from './tracking/video-tracking';
import { track } from "./tracking/tracker";
import { communicator } from "./communicator";

const GPT_LIBRARY_URL = '//www.googletagservices.com/tag/js/gpt.js';

export async function setupAdEngine(
	isOptedIn = false,
	geoRequiresConsent = true,
	isSaleOptOut = false,
	geoRequiresSignal = true,
) {
	const wikiContext = window.ads.context;

	await ads.configure(wikiContext, { isOptedIn, geoRequiresConsent, isSaleOptOut, geoRequiresSignal });

	slots.injectIncontentBoxad();
	videoTracker.register();

	context.push('delayModules', babDetection);
	context.push('delayModules', biddersDelay);

	setupJWPlayer();

	eventService.on(events.AD_SLOT_CREATED, (slot) => {
		console.info(`Created ad slot ${slot.getSlotName()}`);
		bidders.updateSlotTargeting(slot.getSlotName());
	});
	eventService.on(moatYiEvents.MOAT_YI_READY, (data) => {
		pageTracker.trackProp('moat_yi', data);
	});

	await billTheLizardConfigurator.configure();

	if (context.get('state.showAds')) {
		callExternals();
		startAdEngine();
	} else {
		window.wgAfterContentAndJS.push(hideAllAdSlots);
	}

	trackLabradorValues();
	trackLikhoToDW();
	trackTabId();
	trackXClick();
	trackVideoPage();
	taxonomyService.configureComicsTargeting();
}

async function setupJWPlayer() {
	new JWPlayerManager().manage();

	if (!context.get('state.showAds')) {
		return communicator.dispatch({
			type: '[Ad Engine] Setup JWPlayer',
			showAds: false,
			autoplayDisabled: featuredVideoAutoPlayDisabled,
		});
	}

	const timeout = new Promise((resolve) => {
		setTimeout(resolve, context.get('options.maxDelayTimeout'));
	});

	await Promise.race([ timeout, biddersDelay.getPromise() ]);

	communicator.dispatch({
		type: '[Ad Engine] Setup JWPlayer',
		showAds: true,
		autoplayDisabled: featuredVideoAutoPlayDisabled
	})
}

function startAdEngine() {
	if (context.get('state.showAds')) {
		utils.scriptLoader.loadScript(GPT_LIBRARY_URL);

		ads.init();

		window.wgAfterContentAndJS.push(() => {
			slots.injectBottomLeaderboard();
			babDetection.run().then(() => {
				btRec.run();
			});
		});
		slots.injectHighImpact();
		slots.injectFloorAdhesion();

		eventService.on(AdSlot.SLOT_RENDERED_EVENT, (slot) => {
			slot.getElement().classList.remove('default-height');
		});
	}
}

function trackLabradorValues() {
	const cacheStorage = InstantConfigCacheStorage.make();
	const labradorPropValue = cacheStorage.getSamplingResults().join(';');

	if (labradorPropValue) {
		pageTracker.trackProp('labrador', labradorPropValue);
	}
}

/**
 * @private
 */
function trackLikhoToDW() {
	const likhoPropValue = context.get('targeting.likho') || [];

	if (likhoPropValue.length) {
		pageTracker.trackProp('likho', likhoPropValue.join(';'));
	}
}

/**
 * @private
 */
function trackTabId() {
	if (!context.get('options.tracking.tabId')) {
		return;
	}

	window.tabId = sessionStorage.tab_id ? sessionStorage.tab_id : sessionStorage.tab_id = uuid();

	pageTracker.trackProp('tab_id', window.tabId);
}

function trackKruxSegments() {
	const kruxUserSegments = context.get('targeting.ksg') || [];
	const kruxTrackedSegments = context.get('services.krux.trackedSegments') || [];

	const kruxPropValue = kruxUserSegments.filter(segment => kruxTrackedSegments.includes(segment));

	if (kruxPropValue.length) {
		pageTracker.trackProp('krux_segments', kruxPropValue.join('|'));
	}
}

function callExternals() {
	const targeting = context.get('targeting');
	permutive.call();

	bidders.requestBids({
		responseListener: biddersDelay.markAsReady,
	});

	confiant.call();
	durationMedia.call();
	krux.call().then(trackKruxSegments);
	moatYi.call();
	billTheLizard.call(['queen_of_hearts', 'vcr']);
	nielsen.call({
		type: 'static',
		assetid: `fandom.com/${targeting.s0v}/${targeting.s1}/${targeting.artid}`,
		section: `FANDOM ${targeting.s0v.toUpperCase()} NETWORK`,
	});
}

export function registerEditorSavedEvents() {
	var eventId = 'M-FnMTsI';

	window.wgAfterContentAndJS.push(() => {
		// VE editor save complete
		window.ve.trackSubscribe('mwtiming.performance.user.saveComplete', () => {
			krux.fireEvent(eventId);
		});

		// MW/CK editor saving in progress
		window.mw.hook('mwEditorSaved').add(() => {
			krux.fireEvent(eventId);
		});
	});
}

function hideAllAdSlots() {
	Object.keys(context.get('slots')).forEach((slotName) => {
		const element = document.getElementById(slotName);

		if (element) {
			element.classList.add('hidden');
		}
	});
}

function trackXClick() {
	eventService.on(AdSlot.CUSTOM_EVENT, (adSlot, { status }) => {
		if (status === SlotTweaker.SLOT_CLOSE_IMMEDIATELY || status === 'force-unstick') {
			track({
				action: 'click',
				category: 'force_close',
				label: adSlot.getSlotName(),
				trackingMethod: 'analytics',
			});
		}
	});
}

function trackVideoPage() {
	const s2 = context.get('targeting.s2');

	if (['fv-article', 'wv-article'].includes(s2)) {
		track(Object.assign(
			{
				eventName: 'videoplayerevent',
				trackingMethod: 'internal',
			}, {
				category: 'featured-video',
				action: 'pageview',
				label: s2,
			},
		));
	}
}
