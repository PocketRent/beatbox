<?php

class :pr:tabs extends :pr:base {
	attribute :div;

	children (:pr:tab)+;

	protected function compose() {
		$root = <div class="tabsOuter" />;

		$switcher = <pr:button-group />;
		$tabs = <div class="tabs" />;

		$root->appendChild(<div class="tabSwitcher" role="tablist">{$switcher}</div>);
		$root->appendChild($tabs);

		$first = true;
		foreach($this->getChildren() as $tab) {
			$title = $tab->getAttribute('title');
			$id = $tab->getID();
			$tabs->appendChild($tab);
			$icon = $tab->isAttributeSet('icon') ? <pr:icon src={$tab->getAttribute('icon')} /> : '';

			$button = <pr:button role="tab" aria-controls={$id}>{$icon}<span>{$title}</span></pr:button>;
			if ($first) {
				$button->setAttribute('aria-selected', 'true');
				$button->setAttribute('tabindex', '0');
				$first = false;
			} else {
				$button->setAttribute('aria-selected', 'false');
				$button->setAttribute('tabindex', '-1');
			}
			$switcher->appendChild($button);

			$tab->setAttribute('aria-labelledby', $button->getID());
		}

		return $root;
	}
}

class :pr:tab extends :pr:base {
	attribute
		:div,
		string title @required,
		string icon;

	protected $skipTransfer = Set<string> {'title'};

	protected function compose() {
		return <div class="tab hide" id={$this->getID()}>
			{$this->getChildren()}
		</div>;
	}
}
