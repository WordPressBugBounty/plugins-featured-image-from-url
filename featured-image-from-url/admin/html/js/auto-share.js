/*! FIFU Auto-Share — fancybox info modal */
(function ($, window, document) {
    'use strict';

    const vars = window.fifuScriptVars || {};
    const X_CALLBACK_URL = 'https://auto-share.fifu.workers.dev/v2/oauth/x/callback';

    function escapeHtml(str) {
        if (typeof str !== 'string') {
            return '';
        }
        return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
    }

    function normalizeString(value, fallback) {
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed !== '') {
                return trimmed;
            }
        }
        return fallback;
    }

    $(function () {
        $(document).on('click', '#fifu-auto-share-info', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!$.fancybox || !$.isFunction($.fancybox.open)) {
                console.warn('fancyBox is not available to open Auto-Share info.');
                return;
            }

            const infoVars = (vars.shareInfo && typeof vars.shareInfo === 'object') ? vars.shareInfo : {};
            const shareInfo = {
                title: normalizeString(infoVars.title, ''),
                facebook: {
                    page: normalizeString(infoVars.facebook && infoVars.facebook.page, ''),
                    published: normalizeString(infoVars.facebook && infoVars.facebook.published, '')
                },
                instagram: {
                    professional: normalizeString(infoVars.instagram && infoVars.instagram.professional, ''),
                    public: normalizeString(infoVars.instagram && infoVars.instagram.public, '')
                },
                x: {
                    developer: normalizeString(infoVars.x && infoVars.x.developer, ''),
                    permissions: normalizeString(infoVars.x && infoVars.x.permissions, ''),
                    type: normalizeString(infoVars.x && infoVars.x.type, ''),
                    callback: normalizeString(infoVars.x && infoVars.x.callback, ''),
                    client: normalizeString(infoVars.x && infoVars.x.client, '')
                }
            };

            const facebookItems = [
                shareInfo.facebook.page,
                shareInfo.facebook.published
            ].filter(Boolean).map(function (text) {
                return '<li>' + escapeHtml(text) + '</li>';
            }).join('');

            const instagramItems = [
                shareInfo.instagram.professional,
                shareInfo.instagram.public
            ].filter(Boolean).map(function (text) {
                return '<li>' + escapeHtml(text) + '</li>';
            }).join('');

            const xItemsArray = [];
            if (shareInfo.x.developer) {
                xItemsArray.push('<li>' + escapeHtml(shareInfo.x.developer) + ' <code>' + 'https://developer.x.com' + '</code></li>');
            }
            if (shareInfo.x.permissions) {
                xItemsArray.push('<li>' + escapeHtml(shareInfo.x.permissions) + '</li>');
            }
            if (shareInfo.x.type) {
                xItemsArray.push('<li>' + escapeHtml(shareInfo.x.type) + '</li>');
            }
            if (shareInfo.x.callback) {
                xItemsArray.push('<li>' + escapeHtml(shareInfo.x.callback) + ' <code>' + escapeHtml(X_CALLBACK_URL) + '</code></li>');
            }
            if (shareInfo.x.client) {
                xItemsArray.push('<li>' + escapeHtml(shareInfo.x.client) + '</li>');
            }
            const xItems = xItemsArray.join('');

            const modalHtmlParts = [
                '<div class="fifu-auto-share-modal" tabindex="0">'
            ];

            if (shareInfo.title) {
                modalHtmlParts.push('<h2>' + escapeHtml(shareInfo.title) + '</h2>');
            }

            if (facebookItems) {
                modalHtmlParts.push(
                        '<section class="fifu-auto-share-section fifu-auto-share-facebook">',
                        '<div class="fifu-auto-share-section__header">',
                        '<span class="fifu-auto-share-section__label">' + escapeHtml('Facebook') + '</span>',
                        '</div>',
                        '<ul>' + facebookItems + '</ul>',
                        '</section>'
                        );
            }

            if (instagramItems) {
                modalHtmlParts.push(
                        '<section class="fifu-auto-share-section fifu-auto-share-instagram">',
                        '<div class="fifu-auto-share-section__header">',
                        '<span class="fifu-auto-share-section__label">' + escapeHtml('Instagram') + '</span>',
                        '</div>',
                        '<ul>' + instagramItems + '</ul>',
                        '</section>'
                        );
            }

            if (xItems) {
                modalHtmlParts.push(
                        '<section class="fifu-auto-share-section fifu-auto-share-x">',
                        '<div class="fifu-auto-share-section__header">',
                        '<span class="fifu-auto-share-section__label">' + escapeHtml('X') + '</span>',
                        '</div>',
                        '<ul>' + xItems + '</ul>',
                        '</section>'
                        );
            }

            modalHtmlParts.push('</div>');

            const modalHtml = modalHtmlParts.join('');

            $.fancybox.open({
                src: modalHtml,
                type: 'html',
                touch: false,
                smallBtn: true,
                dragToClose: false,
                clickSlide: false,
                clickOutside: false
            });
        });
    });
})(jQuery, window, document);
