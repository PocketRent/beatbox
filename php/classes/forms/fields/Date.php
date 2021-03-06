<?hh

use beatbox\orm\DateTimeType as DateTimeType;

class :bb:form:date extends :bb:form:field {
	attribute
		:bb:form:dropdown,
		:bb:form:number,
		:div,
		bool withDay = true,
		DateTimeInterface value,
		DateTimeInterface min,
		DateTimeInterface max;

	protected ?string $type = 'date';

	protected ImmSet<string> $skipTransfer = ImmSet { 'label', 'value', 'name' };

	protected function buildField() : :div {
		$withDay = $this->getAttribute('withDay');
		$value = $this->getAttribute('value');

		if($value) {
			assert($value instanceof DateTimeInterface);
			$mV = $value->format('n');
			$yV = $value->format('Y');
			$dV = $value->format('j');
		} else {
			$mV = $yV = $dV = '';
		}

		$months = array(
			0 => 'Month',
			1 => 'January - 01',
			2 => 'February - 02',
			3 => 'March - 03',
			4 => 'April - 04',
			5 => 'May - 05',
			6 => 'June - 06',
			7 => 'July - 07',
			8 => 'August - 08',
			9 => 'September - 09',
			10 => 'October - 10',
			11 => 'November - 11',
			12 => 'December - 12'
		);

		if($withDay) {
			$days = range(1, 31);
			$days = [0 => 'Day'] + array_combine($days, $days);
			$days = <bb:form:dropdown
				class="days"
				items={$days}
				name={(string)$this->getAttribute('name') . '[Day]'}
				id={$this->getID()}
				value={$dV} />;
		} else {
			$days = <x:frag />;
		}

		$months = <bb:form:dropdown
			class="months"
			items={$months}
			name={(string)$this->getAttribute('name') . '[Month]'}
			value={$mV} />;
		if(!$withDay) {
			$months->setAttribute('id', $this->getID());
		}

		$years = <bb:form:number
			placeholder="YYYY"
			class="year"
			name={(string)$this->getAttribute('name') . '[Year]'}
			value={$yV} />;

		$this->cascadeAttributes($days);
		$this->cascadeAttributes($months);
		$this->cascadeAttributes($years);

		return <div class="date">
			{$days}
			{$months}
			{$years}
		</div>;
	}

	public function setValue(mixed $value) : :bb:form:date {
		if(is_array($value)) {
			$value = "$value[Year]-$value[Month]-$value[Day]";
			parent::setValue(new DateTimeType($value));
		} else {
			parent::setValue(new DateTimeType((string)$value));
		}
		return $this;
	}
}
