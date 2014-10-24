var ajaxHomeInstantLoad = function(name) {
    var caller = Object.create(ajaxHomeCallPrototype, {
        useSessionStorage: {
            value: true
        },
        sessionStorageBlocks: {
            value: [ name ]
        },
        isJQ: {
            value: (typeof jQuery !== "undefined")
        }
    });
    caller.loadFromStorage();
}
/**
 * Send ajax request to the Magento store in order to insert dynamic content into the
 * static page delivered from Varnish
 */
var ajaxHomeCallPrototype = {

    ajaxHomeUrl: "",
    isJQ: false,
    currentProductId: null,
    useSessionStorage: false,
    sessionStorageKey: 'aoestatic',
    refreshTriggerKey: 'aoestatic_refresh',
    updateTriggerKey: 'aoestatic_update_',
    acceptUpdateEvents: true,
    sessionStorageBlocks: [],
    sessionStorageGroups: {},

    init: function() {
        this.isJQ = (typeof jQuery !== "undefined");
        this.load();
        this.bindEvents();
    },

    canUseSessionStorage: function() {
        return this.isJQ
        && this.useSessionStorage
        && (typeof sessionStorage !== "undefined")
        && (typeof localStorage !== "undefined");
    },

    getPlaceholderElements: function() {
        return this.isJQ ? jQuery('.placeholder') : $$('.placeholder');
    },

    load: function() {
        this.loadFromStorage();
        this.callHome();
    },

    getBlocksToStore: function() {
        return this.sessionStorageBlocks;
    },

    getClearBlockNames: function(area) {
        return this.sessionStorageGroups[area] || [];
    },

    bindEvents: function() {
        if (!(this.isJQ && this.canUseSessionStorage())) {
            return;
        }
        this.bindClearEvents();
        this.bindUpdateEvents();
    },

    clearBlocks: function(area) {
        this.acceptUpdateEvents = false;

        var blockNames = this.getClearBlockNames(area);
        for (var index = 0; index < blockNames.length; index++) {
            this.removeFromStorage(blockNames[index]);
        }
    },

    clearCartBlocks: function() {
        return this.clearBlocks('cart');
    },

    clearCustomerBlocks: function() {
        return this.clearBlocks('customer');
    },

    bindClearEvents: function() {
        if (window.location.pathname.match(/checkout/)
            && !jQuery("body").hasClass('advanced-checkout-cart-index')
        ) {
            this.clearCartBlocks();
        }

        if (window.location.pathname.match(/customer/)) {
            this.clearCustomerBlocks();
            this.clearCartBlocks();
        }

        var $this = this;

        jQuery(window).bind('storage', function(e) {
            var event = e.originalEvent;
            if (event.key == $this.refreshTriggerKey && event.newValue) {
                $this.removeFromStorage(event.newValue, true);
            }
        });

        jQuery(function() {
            jQuery('.add-to-cart').bind('click', function() {
                $this.clearCartBlocks();
            });
        });
    },

    bindUpdateEvents: function() {
        var $this = this;
        jQuery(window).bind('after-replace-block', function(e, data) {
            if (!$this.acceptUpdateEvents) {
                return;
            }

            var block = data.block;

            var key     = block.name;
            var content = block.html;
            var blocks  = {}
            blocks[key] = content;
            $this.writeToStorage(blocks);
            $this.triggerRefreshEvent(key)
        });

        jQuery(window).bind('storage', function(e) {
            if (!$this.acceptUpdateEvents) {
                return;
            }

            var event = e.originalEvent;

            var updateKeyLength = $this.updateTriggerKey.length;

            if (event.key.substr(0, updateKeyLength) == $this.updateTriggerKey && event.newValue) {
                var name = event.key.substr(updateKeyLength);
                var blocks = {};
                blocks[name] = event.newValue;
                $this.writeToStorage(blocks, false, true);
                $this.refreshBlocks(blocks);
            }
        });
    },

    loadFromStorage: function() {
        if (!this.canUseSessionStorage()) {
            return;
        }

        var storedContents = this.getFromStorage();

        if (storedContents) {
            this.replaceBlocks(storedContents);
        }
    },

    triggerRefreshEvent: function(blockKeys) {
        if (typeof blockKeys == "string") {
            blockKeys = [ blockKeys ];
        }
        localStorage.setItem(this.refreshTriggerKey, blockKeys.join(","));
        localStorage.removeItem(this.refreshTriggerKey);
    },

    triggerUpdateEvent: function(name, value) {
        var key = this.updateTriggerKey+name;
        localStorage.setItem(key, value);
        localStorage.removeItem(key);
    },

    getKeysFromStorage: function() {
        var storageKeys = sessionStorage.getItem(this.sessionStorageKey);

        if (!storageKeys) {
            return [];
        }
        return storageKeys.split(",");
    },

    getFromStorage: function() {
        var storageKeys = this.getKeysFromStorage();
        var storedContents = {};
        for (var index = 0; index < storageKeys.length; index++) {
            var key = storageKeys[index];
            storedContents[key] = sessionStorage.getItem(key);
        }
        return storedContents;
    },

    removeFromStorage: function(key, noTriggerRefresh) {
        var storedContents = this.getFromStorage();
        delete storedContents[key];
        sessionStorage.removeItem(key);
        this.writeToStorage(storedContents, key);

        if (!noTriggerRefresh) {
            this.triggerRefreshEvent(key);
        }
    },

    writeToStorage: function(blocks, unsetKey, noTriggerRefresh) {
        var allowedBlocks = this.getBlocksToStore();
        var storageKeys   = this.getKeysFromStorage();
        if (unsetKey) {
            var index = storageKeys.indexOf(unsetKey);
            if (index > -1) {
                storageKeys.splice(index, 1);
            }
        }
        for (var id in blocks) {
            if (!blocks.hasOwnProperty(id)) {
                continue;
            }
            if (allowedBlocks.indexOf(id) > -1) {
                sessionStorage.setItem(id, blocks[id]);
                storageKeys.push(id);

                if (!noTriggerRefresh) {
                    this.triggerUpdateEvent(id, blocks[id]);
                }
            }
        }
        storageKeys = storageKeys.uniq();
        if (storageKeys.length > 0) {
            sessionStorage.setItem(this.sessionStorageKey, storageKeys.join(","))
        } else {
            sessionStorage.removeItem(this.sessionStorageKey);
        }
    },

    refreshBlocks: function(blockContents) {
        this.replaceBlocks(blockContents);

        for (var id in blockContents) {
            if (!blockContents.hasOwnProperty(id)) {
                continue;
            }

            var $element = jQuery('[id="'+id+'"]');
            if ($element.length > 0) {
                var $block = jQuery(blockContents[id]);
                $element.replaceWith($block);
            }
        }
    },

    replaceBlocks: function(blockContents) {
        var $this = this;

        var elems = this.getPlaceholderElements();
        elems.each(function(el) {
            if ($this.isJQ) {
                var $element = jQuery(this);
                var blockRel = $element.attr('rel');
                var blockId  = $element.attr('id');

                var html = blockContents[blockRel] || blockContents[blockId];
                if (html) {
                    var $block = jQuery(html);
                    $element.replaceWith($block);
                }
            } else {
                var $element = $(el);

                var blockId  = $element.readAttribute('id');
                var blockRel = $element.readAttribute('rel');

                var html = blockContents[blockRel] || blockContents[blockId];
                if (html) {
                    $element.replace(html);
                }
            }
        });
    },

    callHome: function() {
        var data = {
            getBlocks: {}
        };
        var counter = 0;
        var elems = this.getPlaceholderElements();

        var $this = this;

        elems.each(function(el) {
            el = $this.isJQ ? jQuery(this) : $(el);
            var rel = $this.isJQ ? el.attr('rel') : el.readAttribute('rel');
            if (rel) {
                data.getBlocks[rel] = rel;
                counter++;
            }
        });

        // add current product
        if (typeof $this.currentProductId !== 'undefined' && $this.currentProductId) {
            data.currentProductId = $this.currentProductId;
        }

        if (typeof data.currentProductId !== 'undefined' || counter > 0) {
            var update = function(response) {
                $this.updateBlocks(response);
            };
            if ($this.isJQ) {
                jQuery.get($this.ajaxHomeUrl, data, update, 'json');
            } else {
                $H(data.getBlocks).each(function(block) {
                    data['getBlocks[' + block[0] + ']'] = block[1];
                });
                new Ajax.Request($this.ajaxHomeUrl, {
                    method: 'get',
                    parameters: data,
                    onSuccess: update
                });
            }
        }
    },

    updateBlocks: function(response) {
        response = this.isJQ ? response : response.transport.responseText.evalJSON();
        if (!response.blocks) {
            return;
        }
        this.writeToStorage(response.blocks);
        this.replaceBlocks(response.blocks);
    }

};
