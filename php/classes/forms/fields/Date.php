<?php

class :pr:form:date extends :pr:form:field {
	attribute
		:pr:form:dropdown,
		:pr:form:number,
		:div,
		bool withDay = true,
		DateTime value,
		DateTime min,
		DateTime max;

	protected $type = 'date';

	protected $skipTransfer = Set<string> { 'label', 'value', 'name' };

	protected function buildField() {
		$withDay = $this->getAttribute('withDay');
		$value = $this->getAttribute('value');

		if($value) {
			$mV = $value->format('n');
			$yV = $value->format('Y');
			$dV = $value->format('j');
		} else {
			$mV = $yV = $dV = '';
		}

		$months = [0 => 'Month', 'January - 01', 'February - 02', 'March - 03', 'April - 04', 'May - 05', 'June - 06', 'July - 07', 'August - 08', 'September - 09', 'October - 10', 'November - 11', 'December - 12'];

		if($withDay) {
			$days = range(1, 31);
			$days = [0 => 'Day'] + array_combine($days, $days);
			$days = <pr:form:dropdown class="days" items={$days} name={$this->getAttribute('name') . '[Day]'} id={$this->getID()} value={$dV} />;
		} else {
			$days = <x:frag />;
		}

		$months = <pr:form:dropdown class="months" items={$months} name={$this->getAttribute('name') . '[Month]'} value={$mV} />;
		if(!$withDay) {
			$months->setAttribute('id', $this->getID());
		}

		$years = <pr:form:number placeholder="YYYY" class="year" name={$this->getAttribute('name') . '[Year]'} value={$yV} />;

		$this->cascadeAttributes($days);
		$this->cascadeAttributes($months);
		$this->cascadeAttributes($years);

		return <div class="date">
			{$days}
			{$months}
			{$years}
		</div>;
	}

	public function setValue($value) {
		if(is_a($value, 'DateTime')) {
			parent::setValue($value);
		} elseif(is_array($value)) {
			$value = "$value[Year]-$value[Month]-$value[Day]";
			parent::setValue(new DateTime($value));
		} else {
			parent::setValue(new DateTime($value));
		}
	}
}
