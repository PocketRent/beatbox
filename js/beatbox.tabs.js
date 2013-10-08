jQuery.entwine('beatbox', function($) {
	$('.tabsOuter').entwine({
		switchTab: function(button, tab) {
			button = button || this.switcher().selectedButton();
			tab = tab || button.getTab();
			this.switcher().unselectAll();
			button.select();
			this.tabs().hideAll();
			tab.show();
		},
		switcher: function() {
			return this.find('.tabSwitcher').first();
		},
		tabs: function() {
			return this.find('.tabs').first();
		}
	});

	$('.tabsOuter .tabSwitcher').entwine({
		onadd: function() {
			if(this.is(':visible')) {
				this.selectedButton().click();
			}
		},
		tabController: function() {
			return this.closest('.tabsOuter');
		},
		selectedButton: function() {
			return this.find('button[aria-selected=true]').first();
		},
		unselectAll: function() {
			this.find('button').unselect();
		}
	});
	$('.tabsOuter .tabSwitcher button').entwine({
		getTab: function() {
			var controls = this.attr('aria-controls');
			return $('#' + controls);
		},
		tabController: function() {
			return this.closest('.tabsOuter');
		},
		select: function() {
			this.attr('aria-selected', 'true');
		},
		unselect: function() {
			this.attr('aria-selected', 'false');
		},
		onclick: function() {
			var tab = this.getTab();
			if(tab.is(':visible')) {
				// early exit
				return false;
			}
			tab.startLoad();
			this.tabController().switchTab(this, tab);
		}
	});
	$('.tabsOuter .tabs').entwine({
		active: function() {
			return this.find('.tab:visible').first();
		},
		hideAll: function() {
			this.find('.tab').hide();
		}
	});
	$('.tabsOuter .tab').entwine({
		startLoad: function() {
			var tab = this, toLoad = tab.data('load');
			if(!toLoad) {
				return;
			}
			tab.addClass('loading');
			$.getJSON(toLoad, { fragments: tab.data('fragment')}).success(function(data) {
				tab.html(data[tab.data('fragment')]);
			}).complete(function() {
				tab.removeClass('loading');
			});
		},
		hide: function() {
			this.addClass('hide');
		},
		show: function() {
			this.removeClass('hide');
		}
	});
});
