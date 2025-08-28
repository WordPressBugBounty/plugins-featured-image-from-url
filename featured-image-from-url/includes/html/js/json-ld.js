(function () {
    if (typeof fifuJsonLd !== 'undefined' && fifuJsonLd.url) {
        // Map string/objects to ImageObject with @id and url; dedup per list
        function toImageObjects(img) {
            var arr = Array.isArray(img) ? img : (img ? [img] : []);
            var out = [];
            var seen = Object.create(null);

            for (var i = 0; i < arr.length; i++) {
                var it = arr[i];
                if (!it)
                    continue;

                if (typeof it === 'string') {
                    if (seen[it])
                        continue;
                    seen[it] = 1;
                    out.push({
                        "@type": "ImageObject",
                        "@id": it,
                        "url": it
                    });
                } else if (typeof it === 'object') {
                    // If already an object, ensure it has @type/@id/url
                    var id = it['@id'] || it.url;
                    if (!id)
                        continue;
                    if (seen[id])
                        continue;
                    seen[id] = 1;

                    var obj = {};
                    for (var k in it)
                        if (Object.prototype.hasOwnProperty.call(it, k))
                            obj[k] = it[k];
                    obj['@type'] = obj['@type'] || 'ImageObject';
                    obj['@id'] = id;
                    obj['url'] = obj['url'] || id;
                    out.push(obj);
                }
            }
            return out;
        }

        var jsonData = {
            "@context": fifuJsonLd["@context"],
            "@type": fifuJsonLd["@type"],
            "url": fifuJsonLd.url
        };

        // Product-level images -> ImageObject[]
        if (fifuJsonLd.image) {
            jsonData.image = toImageObjects(fifuJsonLd.image);
        }

        if (fifuJsonLd.headline) {
            jsonData.headline = fifuJsonLd.headline; // BlogPosting
        }

        if (fifuJsonLd.name) {
            jsonData.name = fifuJsonLd.name; // Product
        }

        var commentBegin = document.createComment(' FIFU:jsonld:begin');
        document.head.appendChild(commentBegin);

        var script = document.createElement('script');
        script.type = 'application/ld+json';
        script.textContent = JSON.stringify(jsonData);
        document.head.appendChild(script);

        var commentEnd = document.createComment(' FIFU:jsonld:end');
        document.head.appendChild(commentEnd);
    }
})();
