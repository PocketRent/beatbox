<?hh

class :bb:form:time extends :bb:form:field {
	attribute
		:bb:form:dropdown,
		:div,
		int minuteStep = 1,
		int minHour = 0,
		int maxHour = 23;

	protected $type = 'time';

	protected $skipTransfer = Set<string> { 'label', 'name' };

	protected function buildField() {
		$step = $this->getAttribute('minuteStep');
		$min = $this->getAttribute('minHour');
		$max = $this->getAttribute('maxHour');

		if($step > 60 || $step < 1) {
			throw new InvalidArgumentException('minuteStep must be in the range [1, 60]. ' . $step . ' given.');
		}
		if($min < 0 || $min > 23) {
			throw new InvalidArgumentException('minHour must be in the range [0, 23]. ' . $min . ' given.');
		}
		if($max < 0 || $max > 23) {
			throw new InvalidArgumentException('maxHour must be in the range [0, 23]. ' . $max . ' given.');
		}

		if($min > $max) {
			throw new InvalidArgumentException('minHour can not be greater than maxHour');
		}

		$hours = range($min, $max);
		$hours = [-1 => 'Hour'] + array_combine($hours, $hours);
		$minutes = function() use ($step) {
			yield -1 => 'Minutes';
			for($i = 0; $i < 60; $i += $step) yield $i => sprintf('%02d', $i);
		};

		$value = $this->getAttribute('value');
		if($value) {
			if(strpos($value, ':')) {
				list($hV, $mV) = explode(':', $value, 2);
			} else {
				$hV = date('G', $value);
				$mV = date('i', $value);
			}
		} else {
			$hV = '-1';
			$mV = '-1';
		}

		$hours = <bb:form:dropdown class="hours" items={$hours} name={$this->getAttribute('name') . '[Hour]'} id={$this->getID()} value={$hV} />;
		$minutes = <bb:form:dropdown class="minutes" items={$minutes()} name={$this->getAttribute('name') . '[Minute]'} value={$mV} />;

		$this->cascadeAttributes($hours);
		$this->cascadeAttributes($minutes);

		return <div class="time">{$hours}:{$minutes}</div>;
	}

	public function setValue($value) {
		if(is_a($value, 'DateTime')) {
			parent::setValue($value->Format('H:i'));
		} elseif(is_array($value)) {
			$value = "$value[Hour]-$value[Minute]";
			parent::setValue($value);
		} else {
			parent::setValue($value);
		}
	}

	public function validate() {
		$value = $this->getAttribute('value');
		if($value) {
			list($hours, $minutes) = explode(':', $value, 2);
			if($hours < $this->getAttribute('minHour')) {
				$this->valid = false;
				$this->error = 'Hours can not be before ' . $this->getAttribute('minHour');
			} elseif($hours > $this->getAttribute('maxHour')) {
				$this->valid = false;
				$this->error = 'Hours can not be after ' . $this->getAttribute('minHour');
			} elseif($minutes % $this->getAttribute('minuteStep') != 0) {
				$this->valid = false;
				$this->error = 'Minutes must be a multiple of ' . $this->getAttribute('minuteStep');
			}
		}
		return parent::validate();
	}
}
