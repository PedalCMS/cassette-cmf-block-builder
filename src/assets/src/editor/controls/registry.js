import Text from './text';
import Textarea from './textarea';
import Select from './select';
import Toggle from './toggle';
import CheckboxGroup from './checkbox-group';
import Radio from './radio';
import NumberField from './number';
import Email from './email';
import Url from './url';
import DateField from './date';
import Color from './color';
import Media from './media';
import ToggleGroup from './toggle-group';
import NoticeControl from './notice';
import StaticHtml from './static-html';
import Separator from './separator';
import Heading from './heading';
import Help from './help';
import ToolbarButtonControl from './toolbar-button';
import ToolbarToggle from './toolbar-toggle';
import ToolbarDropdown from './toolbar-dropdown';
import ToolbarMenu from './toolbar-menu';
import ToolbarAlign from './toolbar-align';
import SiblingField from './sibling-field';
import Conditions from './conditions';
import Unit from './unit';
import Box from './box';
import Spacing from './spacing';
import Border from './border';
import BorderBox from './border-box';
import Shadow from './shadow';
import Angle from './angle';
import AlignmentMatrix from './alignment-matrix';
import FontSize from './font-size';
import FontFamily from './font-family';
import FontAppearance from './font-appearance';
import LineHeight from './line-height';
import LetterSpacing from './letter-spacing';
import Icon from './icon';
import Code from './code';
import KeyValue from './key-value';
import OptionsList from './options-list';
import Duotone from './duotone';
import ImageSize from './image-size';
import QueryBuilder from './query-builder';
import Link from './link';
import MediaGallery from './media-gallery';
import ToolbarLink from './toolbar-link';
import RichTextControl from './rich-text';
import EntitySelect from './entity-select';
import PostSelect from './post-select';
import TermSelect from './term-select';
import UserSelect from './user-select';
import TaxonomySelect from './taxonomy-select';
import Unsupported from './unsupported';

/**
 * Control type -> component registry. "Adding a control = one JS file + one
 * Control_Catalog entry" (design plan) — this is the one place a new
 * control's file gets wired in.
 */
const registry = {
	text: Text,
	textarea: Textarea,
	select: Select,
	toggle: Toggle,
	checkbox_group: CheckboxGroup,
	radio: Radio,
	number: NumberField,
	email: Email,
	url: Url,
	date: DateField,
	color: Color,
	media: Media,
	toggle_group: ToggleGroup,
	notice: NoticeControl,
	static_html: StaticHtml,
	separator: Separator,
	heading: Heading,
	help: Help,
	toolbar_button: ToolbarButtonControl,
	toolbar_toggle: ToolbarToggle,
	toolbar_dropdown: ToolbarDropdown,
	toolbar_menu: ToolbarMenu,
	toolbar_align: ToolbarAlign,
	sibling_field: SiblingField,
	conditions: Conditions,
	unit: Unit,
	box: Box,
	spacing: Spacing,
	border: Border,
	border_box: BorderBox,
	shadow: Shadow,
	angle: Angle,
	alignment_matrix: AlignmentMatrix,
	font_size: FontSize,
	font_family: FontFamily,
	font_appearance: FontAppearance,
	line_height: LineHeight,
	letter_spacing: LetterSpacing,
	icon: Icon,
	code: Code,
	key_value: KeyValue,
	options_list: OptionsList,
	duotone: Duotone,
	image_size: ImageSize,
	query_builder: QueryBuilder,
	link: Link,
	media_gallery: MediaGallery,
	toolbar_link: ToolbarLink,
	rich_text: RichTextControl,
	entity_select: EntitySelect,
	post_select: PostSelect,
	term_select: TermSelect,
	user_select: UserSelect,
	taxonomy_select: TaxonomySelect,
};

/**
 * Register (or override) a control type's component. This is the
 * undocumented JS escape hatch the design plan describes
 * (window.cassetteCmfBlocks.registerControlType()) — the library's own
 * controls above are wired in exactly this way, so a consumer willing to
 * read the source has the same power. Deliberately not documented publicly:
 * the library's stated contract is PHP/JSON-only, zero consumer JS.
 *
 * @param {string}   type      Control type identifier.
 * @param {Function} Component Component receiving { field, value, onChange, type, scope }.
 */
export function registerControlType( type, Component ) {
	registry[ type ] = Component;
}

/**
 * Resolve a control type to its component, falling back to Unsupported for
 * any type without a registered component.
 *
 * @param {string} type Control type identifier.
 * @return {Function} The component.
 */
export function getControlComponent( type ) {
	return registry[ type ] || Unsupported;
}
