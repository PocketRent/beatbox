<?hh

class :bb:icon extends :bb:base {
	attribute :img;

	protected ImmSet<string> $skipTransfer = ImmSet {'src'};

	protected function compose() : :x:composable-element {
		$src = (string)$this->getAttribute('src');

		$base = BASE_DIR . '/';

		if(have_svg()) {
			$available = Vector {'svgz', 'svg', 'png', 'jpeg', 'gif'};
		} else {
			$available = Vector {'png', 'jpeg', 'gif'};
		}
		$usedExt = false;
		foreach($available as $ext) {
			if(realpath($base . $src . '.' . $ext)) {
				$src .= '.' . $ext;
				$usedExt = $ext;
				break;
			}
		}
		if(have_inline_svg() && ($usedExt == 'svg' || $usedExt == 'svgz')) {
			$dom = new \DOMDocument();
			$dom->load($base.$src);
			$svg = $dom->getElementsByTagName('svg')->item(0);
			foreach($this->getAttributes() as $name => $value) {
				if($this->skipTransfer->contains($name)) {
					continue;
				}
				if($name == 'class') {
					assert(is_string($value));
					$value = (string)$svg->getAttribute('class') . ' ' . $value;
				}
				$svg->setAttribute($name, $value);
			}
			self::formatDOMElement($svg);
			$svg = $dom->saveXML($svg);
			return <bb:raw>{$svg}</bb:raw>;
		}
		return <img src={$src} />;
	}

	/**
	 * Format a DOM element.
	 *
	 * This function takes in a DOM element, and removes whitespace
	 * to make it smaller for embedding.
	 *
	 * @param DOMElement $root  The root element which should be formatted.
	 */
	protected static function formatDOMElement(DOMElement $root) : void {
		/* Check what this element contains. */
		$fullText = ''; /* All text in this element. */
		$textNodes = Vector {}; /* Text nodes which should be deleted. */
		$childNodes = Vector {}; /* Other child nodes. */
		for ($i = 0; $i < $root->childNodes->length; $i++) {
			$child = $root->childNodes->item($i);

			if($child instanceof DOMText) {
				$textNodes[] = $child;
				$fullText .= $child->wholeText;

			} else if ($child instanceof DOMElement) {
				$childNodes[] = $child;

			} else {
				/* Unknown node type. We don't know how to format this. */
				return;
			}
		}

		$fullText = trim($fullText);
		if (strlen($fullText) > 0) {
			/* We contain text. */
			$hasText = TRUE;
		} else {
			$hasText = FALSE;
		}

		$hasChildNode = (count($childNodes) > 0);

		if ($hasText && $hasChildNode) {
			/* Element contains both text and child nodes - we don't know how to format this one. */
			return;
		}

		/* Remove text nodes. */
		foreach ($textNodes as $node) {
			$root->removeChild($node);
		}

		if ($hasText) {
			/* Only text - add a single text node to the element with the full text. */
			$root->appendChild(new DOMText($fullText));
			return;

		}

		if (!$hasChildNode) {
			/* Empty node. Nothing to do. */
			return;
		}

		/* Element contains only child nodes - add indentation before each one, and
		 * format child elements.
		 */
		foreach ($childNodes as $node) {
			/* Format child elements. */
			self::formatDOMElement($node);
		}
	}

}

//TODO: Implement thumbnails
class :bb:thumbnail extends :bb:icon {
	private ?beatbox\Asset $asset = null;

	public function setAsset(\beatbox\Asset $asset) : void {
		$this->asset = $asset;
	}
}
