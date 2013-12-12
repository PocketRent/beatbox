<?hh

class :bb:tabs extends :bb:base {
	attribute :div;

	children (:bb:tab)+;

	protected function compose() : :div {
		$root = <div class="tabsOuter" />;

		$switcher = <bb:button-group />;
		$tabs = <div class="tabs" />;

		$root->appendChild(<div class="tabSwitcher" role="tablist">{$switcher}</div>);
		$root->appendChild($tabs);

		$first = true;
		foreach($this->getChildren() as $tab) {
			$title = $tab->getAttribute('title');
			$id = $tab->getID();
			$tabs->appendChild($tab);
			$icon = $tab->isAttributeSet('icon') ? <bb:icon src={$tab->getAttribute('icon')} /> : '';

			$button = <bb:button role="tab" aria-controls={$id}>{$icon}<span>{$title}</span></bb:button>;
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

class :bb:tab extends :bb:base {
	attribute
		:div,
		string title @required,
		string icon;

	protected Set<string> $skipTransfer = Set {'title'};

	protected function compose() : :div {
		return <div class="tab hide" id={$this->getID()}>
			{$this->getChildren()}
		</div>;
	}
}
