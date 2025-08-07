// npm install
// npm run build
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody, Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import React from 'react';
import domReady from '@wordpress/dom-ready';

const { toggleEditorPanelEnabled } = wp.data.dispatch('core/edit-post');
const { isEditorPanelEnabled } = wp.data.select('core/edit-post');

export default function Edit({ attributes, setAttributes }) {
	/* ─────────────────────────────────────
	   Attributes & helpers
	───────────────────────────────────── */
	const blockProps = useBlockProps();
	const { imageUrl, altText } = attributes;
	const [imgError, setImgError] = React.useState(false);

	/* ─────────────────────────────────────
	   1.  Meta → Block (run once on mount)
	───────────────────────────────────── */
	useEffect(() => {
		const metaInput = document.getElementById('fifu_input_url');
		const altInput = document.getElementById('fifu_input_alt');
		let changed = false;
		let newAttrs = {};
		if (metaInput) {
			const v = metaInput.value.trim();
			if (v && v !== imageUrl) {
				newAttrs.imageUrl = v;
				changed = true;
			}
		}
		if (altInput) {
			const alt = altInput.value.trim();
			if (alt && alt !== altText) {
				newAttrs.altText = alt;
				changed = true;
			}
		}
		if (changed) setAttributes(newAttrs);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []); // run only once

	/* ─────────────────────────────────────
	   2.  Block → Meta (sync while block exists)
	───────────────────────────────────── */
	useEffect(() => {
		const metaInput = document.getElementById('fifu_input_url');
		const altInput = document.getElementById('fifu_input_alt');
		if (metaInput && metaInput.value !== (imageUrl || '')) {
			if (typeof window.removeImage === 'function') window.removeImage(false);
			metaInput.value = imageUrl || '';
			metaInput.dispatchEvent(new Event('input', { bubbles: true }));
			metaInput.dispatchEvent(new Event('change', { bubbles: true }));
			if (typeof window.previewImage === 'function') setTimeout(window.previewImage, 100);
		}
		if (altInput && altInput.value !== (altText || '')) {
			altInput.value = altText || '';
			altInput.dispatchEvent(new Event('input', { bubbles: true }));
			altInput.dispatchEvent(new Event('change', { bubbles: true }));
	 }

		// Sync custom fields in the custom fields table
		const rows = document.querySelectorAll('#the-list tr');
		rows.forEach(row => {
			const keyInput = row.querySelector('input[type="text"][name$="[key]"]');
			const valueTextarea = row.querySelector('textarea[name$="[value]"]');
			if (keyInput && valueTextarea) {
				if (keyInput.value === 'fifu_image_url' && valueTextarea.value !== (imageUrl || '')) {
					valueTextarea.value = imageUrl || '';
					valueTextarea.dispatchEvent(new Event('input', { bubbles: true }));
					valueTextarea.dispatchEvent(new Event('change', { bubbles: true }));
			 }
				if (keyInput.value === 'fifu_image_alt' && valueTextarea.value !== (altText || '')) {
					valueTextarea.value = altText || '';
					valueTextarea.dispatchEvent(new Event('input', { bubbles: true }));
					valueTextarea.dispatchEvent(new Event('change', { bubbles: true }));
			 }
			}
		});

		window.__fifuLastImageUrl = imageUrl || '';
		window.__fifuLastAltText = altText || '';
	}, [imageUrl, altText]);

	/* ─────────────────────────────────────
	   3.  Show / hide meta box + safe sync
	───────────────────────────────────── */
	if (
		typeof window !== 'undefined' &&
		typeof wp !== 'undefined' &&
		wp.data &&
		wp.data.select
	) {
		if (!window.__fifuMetaBoxSubscribed) {
			window.__fifuMetaBoxSubscribed = true;
			window.__fifuSyncing = false; // re‑entrancy flag

			function updateImageUrlMetaBoxVisibility() {
				const blocks = wp.data.select('core/block-editor').getBlocks();
				const hasBlock = blocks.some((b) => b.name === 'fifu/image');
				const metaBox = document.getElementById('imageUrlMetaBox');
				const metaInput = document.getElementById('fifu_input_url');
				const altInput = document.getElementById('fifu_input_alt');

				if (metaBox) metaBox.style.display = hasBlock ? 'none' : '';

				const prev = window.__fifuPrevHasBlock;
				if (prev === undefined) window.__fifuPrevHasBlock = hasBlock;

				/* Meta → new block (first insertion) */
				if (hasBlock && prev === false && metaInput && !window.__fifuSyncing) {
					const v = metaInput.value.trim();
					const alt = altInput ? altInput.value.trim() : '';
					const target = blocks.find((b) => b.name === 'fifu/image');
					if (target && (v || alt)) {
						window.__fifuSyncing = true;
						let attrs = {};
						if (v) attrs.imageUrl = v;
						if (alt) attrs.altText = alt;
						wp.data
							.dispatch('core/block-editor')
							.updateBlockAttributes(target.clientId, attrs);
						// clear flag in next tick
						setTimeout(() => (window.__fifuSyncing = false), 0);
					}
				}

				/* Block removed → restore value into meta box */
				if (!hasBlock && prev === true) {
					if (metaInput) {
						metaInput.value = window.__fifuLastImageUrl || '';
						metaInput.dispatchEvent(new Event('input', { bubbles: true }));
						metaInput.dispatchEvent(new Event('change', { bubbles: true }));
					}
					if (altInput) {
						altInput.value = window.__fifuLastAltText || '';
						altInput.dispatchEvent(new Event('input', { bubbles: true }));
						altInput.dispatchEvent(new Event('change', { bubbles: true }));
					}
				}

				window.__fifuPrevHasBlock = hasBlock;
			}

			function observeMetaBox() {
				if (document.getElementById('imageUrlMetaBox')) {
					updateImageUrlMetaBoxVisibility();
					wp.data.subscribe(updateImageUrlMetaBoxVisibility);
				} else {
					/* Wait until meta box appears in DOM */
					const observer = new MutationObserver(() => {
						if (document.getElementById('imageUrlMetaBox')) {
							updateImageUrlMetaBoxVisibility();
							wp.data.subscribe(updateImageUrlMetaBoxVisibility);
							observer.disconnect();
						}
					});
					observer.observe(document.body, { childList: true, subtree: true });
				}
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', observeMetaBox);
			} else {
				observeMetaBox();
			}
		}
	}

	/* ─────────────────────────────────────
	   Featured Image Panel Sync
	───────────────────────────────────── */
	const syncFeaturedPanel = () => {
		const hasFifu = wp.data
			.select('core/block-editor')
			.getBlocks()
			.some((b) => b.name === 'fifu/image');

		// Check if FIFU meta box has an image URL
		const metaInput = document.getElementById('fifu_input_url');
		const hasFifuMetaUrl = metaInput && metaInput.value && metaInput.value.trim();

		const enabled = isEditorPanelEnabled
			? isEditorPanelEnabled('featured-image')
			: true; // fallback WP < 6.4

		if ((hasFifu || hasFifuMetaUrl) && enabled) {
			// Hide WP featured image panel if FIFU block or meta box has image
			toggleEditorPanelEnabled('featured-image');
		} else if (!hasFifu && !hasFifuMetaUrl && !enabled) {
			// Show WP featured image panel if neither block nor meta box has image
			toggleEditorPanelEnabled('featured-image');
		}
	};

	syncFeaturedPanel(); // primeira execução
	wp.data.subscribe(syncFeaturedPanel); // mantém em sincronia

	/* ─────────────────────────────────────
	   Block UI
	───────────────────────────────────── */
	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={window.fifuBlockStrings.label.settings}>
					<TextControl
						label={window.fifuBlockStrings.label.image}
						value={imageUrl || ''}
						placeholder={window.fifuBlockStrings.placeholder.paste}
						onChange={(value) => {
							setAttributes({ imageUrl: value });
							setImgError(false); // reset error on change
						}}
					/>
					{imageUrl && (
						<>
							<TextControl
								label={window.fifuBlockStrings.label.alt}
								value={altText}
								onChange={(value) => setAttributes({ altText: value })}
							/>
							<div style={{ fontSize: '12px', color: '#666', marginBottom: '8px' }}>
								{window.fifuBlockStrings.help.alt}
							</div>
							<Button
								isLink
								isDestructive
								onClick={() => {
									setAttributes({ imageUrl: '', altText: '' });
									setImgError(false);
								}}
								style={{ marginTop: 8 }}
							>
								{window.fifuBlockStrings.link.remove}
							</Button>
						</>
					)}
				</PanelBody>
			</InspectorControls>

			{imageUrl ? (
				<div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: '100%' }}>
					<img
						src={
							imgError
								? 'https://storage.googleapis.com/featuredimagefromurl/image-not-found-a.jpg'
								: imageUrl
						}
						alt={altText}
						style={{
							maxWidth: '100%',
							borderRadius: 4,
							border: '1px solid #eee',
							background: '#fff',
						}}
						onError={() => setImgError(true)}
					/>
				</div>
			) : (
				<div
					style={{
						display: 'flex',
						flexDirection: 'column',
						alignItems: 'center',
						justifyContent: 'center',
						width: '100%',
						minHeight: '180px',
						background: '#f0f0f0',
						border: '1px dashed #ccc',
						borderRadius: '4px',
						color: '#888',
						margin: '12px 0',
					}}
				>
					<span>{window.fifuBlockStrings.label.set}</span>
				</div>
			)}
		</div>
	);
}
