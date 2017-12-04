<?php
/**
  * Create a vCard
  *
  * Known issues:
  *   * Date-time values not supported for `BDAY` field (only date values). No plans to implement.
  *   * Text values not supported for `TZ` field (only UTC-offset values). No plans to implement.
  *   * Binary photo data not supported for `LOGO` field (URL-referenced values only). No plans to implement.
  *   * The following vCard elements are not currently supported (no plans to implement):
  *     * AGENT
  *     * SOUND
  *     * KEY
  *
  * Inspired by https://github.com/jeroendesloovere/vcard
  *
  * @author Jared Howland <contacts@jaredhowland.com>
  * @version 2017-12-04
  * @since 2016-10-05
  *
  */

namespace contacts;

/**
 * vCard class to create a vCard
 */
class vcard implements contactInterface {
  use shared;

  /**
   * @var array $properties Array of properties added to the vCard object
   */
  private $properties;

  /**
   * @var array $multiple_properties_allowed Array of properties that can be set more than once
   */
  private $multiple_properties_allowed = array(
    'EMAIL',
    'ADR',
    'LABEL',
    'TEL',
    'EMAIL',
    'URL',
    'X-',
    'CHILD'
  );

  /**
   * @var array $valid_address_types Array of valid address types
   */
  private $valid_address_types = array(
    'dom',
    'intl',
    'postal',
    'parcel',
    'home',
    'work',
    'pref'
  );

  /**
   * @var array $valid_telephone_types Array of valid telephone types
   */
  private $valid_telephone_types = array(
    'home',
    'msg',
    'work',
    'pref',
    'voice',
    'fax',
    'cell',
    'video',
    'pager',
    'bbs',
    'modem',
    'car',
    'isdn',
    'pcs',
    'iphone' // Custom type
  );

  /**
   * @var array $valid_classifications Array of valid classification types
   */
  private $valid_classifications = array(
    'PUBLIC',
    'PRIVATE',
    'CONFIDENTIAL'
  );

  /**
   * @var int $extended_item_count Count of custom iOS elements set
   */
  private $extended_item_count = 1;

  /**
   * @var array $defined_elements Array of defined vCard elements added to the vCard object
   */
  private $defined_elements;

  /**
   * Print out properties and define elements to help with debugging
   *
   * @param null
   * @return null
   */
  public function debug() {
    echo "<pre>**PROPERTIES**\n";
    print_r($this->properties);
    echo "\n\n**DEFINED ELEMENTS**\n";
    print_r($this->defined_elements);
  }

  /**
   * Add full name to vCard
   *
   * RFC 2426 pp. 7–8
   *
   * This type is based on the semantics of the X.520
   * Common Name attribute. The property MUST be present in the vCard
   * object.
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.1.1 RFC 2426 Section 3.1.1 (pp. 7-8)
   * @param string $name Full name
   * @return null
   */
  public function add_full_name($name) {
    $this->construct_element('FN', $name);
  }

  /**
   * Add name to vCard
   *
   * RFC 2426 p. 8
   *
   * This type is based on the semantics of the X.520 individual name
   * attributes. The property MUST be present in the vCard object.
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.1.2 RFC 2426 Section 3.1.2 (p. 8)
   * @param string $last_name Family name
   * @param string $first_name Given name. Default: null
   * @param string $additional_name Middle name(s). Comma-delimited list. Default: null
   * @param string $prefix Honorific prefix(es). Comma-delimited list. Default: null
   * @param string $suffix Honorific suffix(es). Comma-delimited list. Default: null
   * @return null
   */
  public function add_name($last_name, $first_name = null, $additional_name = null, $prefix = null, $suffix = null) {
    $this->construct_element('N', array($last_name, $first_name, $additional_name, $prefix, $suffix));
  }

  /**
   * Add nickname(s) to vCard
   *
   * RFC 2426 pp. 8–9
   *
   * The nickname is the descriptive name given instead
   * of or in addition to the one belonging to a person, place, or thing.
   * It can also be used to specify a familiar form of a proper name
   * specified by the `FN` or `N` types.
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.1.3 RFC 2426 Section 3.1.3 (pp. 8-9)
   * @param string|array $name Nickname(s). Comma-delimited list of nicknames (or array)
   * @return null
   */
  public function add_nickname($name) {
    $name = is_array($name) ? $name : explode(',', $name);
    $this->construct_element('NICKNAME', array($name));
  }

  /**
   * Add photo. Not currently supported.
   *
   * RFC 2426 pp. 9-10
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.1.4 RFC 2426 Section 3.1.4 (pp. 9-10)
   * @param string $photo URL-referenced or base-64 encoded photo
   * @param bool $isUrl Optional. Is it a URL-referenced photo or a base-64 encoded photo. Default: `true`
   */
  public function add_photo($photo, $isUrl = true) {
    if ($isUrl) {
      // Set directly rather than going through $this->construct_element to avoid escaping valid URL characters
      if(!empty($this->sanitize_url($photo))) {
        $this->set_property('PHOTO', vsprintf(\contacts\config::get('PHOTO-BINARY'), array('JPEG', base64_encode($this->get_data($photo)))));
      }
    } else {
      $this->set_property('PHOTO', vsprintf(\contacts\config::get('PHOTO-BINARY'), array('JPEG', $photo)));
    }
  }

  /**
   * Add birthday to vCard
   *
   * RFC 2426 p. 10
   *
   * Standard allows for date-time values. Not supported in this class.
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.1.5 RFC 2426 Section 3.1.5 (p. 10)
   * @param int $year Year of birth. If no year given, use iOS custom date field to indicate birth month and day only. Default: null
   * @param int $month Month of birth.
   * @param int $day Day of birth.
   * @return null
   */
  public function add_birthday($year = null, $month, $day) {
    if($year) {
      $this->construct_element('BDAY', array($year, $month, $day));
    } else {
      $this->defined_elements['BDAY'] = true; // Define `BDAY` element
      $this->construct_element('BDAY-NO-YEAR', array($month, $day));
    }
  }

  /**
   * Add address to vCard
   *
   * RFC 2426 pp. 10–11
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.2.1 RFC 2426 Section 3.2.1 (pp. 10-11)
   * @param string $po_box Post office box number
   * @param string $extended Extended address
   * @param string $street Street address
   * @param string $city City
   * @param string $state State/province
   * @param int $zip Postal code (5 digits per United States standard)
   * @param string $country Country
   * @param string|array $type Comma-delimited list of address types
   *   * Valid `$type`s:
   *      * `dom` - domestic delivery address
   *      * `intl` - international delivery address
   *      * `postal` - postal delivery address
   *      * `parcel` - parcel delivery address
   *      * `home` - residence delivery address
   *      * `work` - work delivery address
   *      * `pref` - preferred delivery address when more than one address is specified
   *   * Default: `intl,postal,parcel,work`
   * @return null
   */
  public function add_address($po_box = null, $extended = null, $street = null, $city = null, $state = null, $zip = null, $country = null, $type = null) {
    $type = is_array($type) ? $type : explode(',', $type);
    // Make sure all `$type`s are valid. If invalid `$type`(s), revert to standard default.
    $type = $this->in_array_all($type, $this->valid_address_types) ? $type : 'intl,postal,parcel,work';
    $this->construct_element('ADR', array($type, $po_box, $extended, $street, $city, $state, $zip, $country));
  }

  /**
   * Add mailing label to vCard
   *
   * RFC 2426 p. 12
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.2.2 RFC 2426 Section 3.2.2 (p. 12)
   * @param string $label Mailing label
   * @param string|array $type Comma-delimited list of mailing label types (or array)
   *   * Valid `$type`s:
   *      * `dom` - domestic delivery address
   *      * `intl` - international delivery address
   *      * `postal` - postal delivery address
   *      * `parcel` - parcel delivery address
   *      * `home` - residence delivery address
   *      * `work` - work delivery address
   *      * `pref` - preferred delivery address when more than one address is specified
   *   * Default: `intl,postal,parcel,work`
   * @return null
   */
  public function add_label($label, $type = null) {
    $type = is_array($type) ? $type : explode(',', $type);
    // Make sure all `$type`s are valid. If invalid `$type`(s), revert to standard default.
    $type = $this->in_array_all($type, $this->valid_address_types) ? $type : 'intl,postal,parcel,work';
    $this->construct_element('LABEL', array($type, $label));
  }

  /**
   * Add telephone number to vCard
   *
   * RFC 2426 p. 13
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.3.1 RFC 2426 Section 3.3.1 (p. 13)
   * @param int $phone Phone number (numbers only)
   * @param string|array $type Comma-delimited list of telephone types (or array)
   *   * Valid `$type`s:
   *      * `home` - telephone number associated with a residence
   *      * `msg` - telephone number has voice messaging support
   *      * `work` - telephone number associated with a place of work
   *      * `pref` - preferred-use telephone number
   *      * `voice` - voice telephone number
   *      * `fax` - facsimile telephone number
   *      * `cell` - cellular telephone number
   *      * `video` - video conferencing telephone number
   *      * `pager` - paging device telephone number
   *      * `bbs` - bulletin board system telephone number
   *      * `modem` - MODEM connected telephone number
   *      * `car` - car-phone telephone number
   *      * `isdn` - ISDN service telephone number
   *      * `pcs` - personal communication services telephone number
   *      * `iphone` - Non-standard type to indicate phone is an iPhone
   *   * Default: `voice`
   * @return null
   */
  public function add_telephone($phone, $type = null) {
    $type = is_array($type) ? $type : explode(',', $type);
    // Make sure all `$type`s are valid. If invalid `$type`(s), revert to standard default.
    $type = $this->in_array_all($type, $this->valid_telephone_types) ? $type : 'voice';
    $this->construct_element('TEL', array($type, $this->sanitize_phone($phone)));
  }

  /**
   * Add email address to vCard
   *
   * RFC 2426 p. 14
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.3.2 RFC 2426 Section 3.3.2 (p. 14)
   * @param string $email Email address
   * @param string|array $type Comma-delimited list of email address types (or array)
   *   * Valid `$type`s:
   *      * `internet` - Internet addressing type
   *      * `x400` - X.400 addressing type
   *      * `pref` - preferred-use email address when more than one is specified
   *      * Another IANA registered address type can also be specified
   *      * A non-standard value can also be specified
   *   * Default: `internet`
   * @return null
   */
  public function add_email($email, $type = null) {
    $type = empty($type) ? 'internet' : $type;
    $type = is_array($type) ? $type : explode(',', $type);
    $this->construct_element('EMAIL', array($type, $this->sanitize_email($email)));
  }

  /**
   * Add email software to vCard
   *
   * RFC 2426 pp. 14-15
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.3.3 RFC 2426 Section 3.3.3 (pp. 14-15)
   * @param string $mailer Software used by recipient to send/receive email
   * @return null
   */
  public function add_mailer($mailer) {
    $this->construct_element('MAILER', $mailer);
  }

  /**
   * Add time zone to vCard
   *
   * RFC 2426 p. 15
   *
   * Standard allows for UTC to be represented as a single text value. Not supported in this class.
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.4.1 RFC 2426 Section 3.4.1 (p. 15)
   * @link http://www.iana.org/time-zones Internet Assigned Numbers Authority (IANA) Time Zone Database
   * @param string $time_zone Time zone (UTC-offset) as a number between -14 and +12 (inclusive - do not zero-pad). Examples: `-7`, `-12`, `-12:00`, `10:30`
   * @return null
   */
  public function add_time_zone($time_zone) {
    $this->construct_element('TZ', $this->sanitize_time_zone($time_zone));
  }

  /**
   * Add latitude and longitude to vCard
   *
   * RFC 2426 pp. 15-16
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.4.2 RFC 2426 Section 3.4.2 (pp. 15-16)
   * @param string $lat Geographic Positioning System latitude (decimal) (must be a number between -90 and 90)
   *
   * **FORMULA**: decimal = degrees + minutes/60 + seconds/3600
   * @param string $long Geopgraphic Positioning System longitude (decimal) (must be a number between -180 and 180)
   *
   * **FORMULA**: decimal = degrees + minutes/60 + seconds/3600
   * @return null
   */
  public function add_lat_long($lat, $long) {
    $this->construct_element('GEO', $this->sanitize_lat_long($lat, $long));
  }

  /**
   * Add job title to vCard
   *
   * RFC 2426 pp. 16-17
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.5.1 RFC 2426 Section 3.5.1 (pp. 16-17)
   * @param string $title Job title
   * @return null
   */
  public function add_title($title) {
    $this->construct_element('TITLE', $title);
  }

  /**
   * Add role, occupation, or business category to vCard
   *
   * RFC 2426 p. 17
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.5.2 RFC 2426 Section 3.5.2 (p. 17)
   * @param string $role Job role
   * @return null
   */
  public function add_role($role) {
    $this->construct_element('ROLE', $role);
  }

  /**
   * Add logo. Not currently supported.
   *
   * RFC 2426 pp. 17-18
   *
   * Standard allows for binary photo data. Not supported in this class (URL-referenced photos only)
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.5.3 RFC 2426 Section 3.5.3 (pp. 17-18)
   * @param null $logo Not supported
   */
  public function add_logo($logo) {
    // Set directly rather than going through $this->construct_element to avoid escaping valid URL characters
    if(!empty($this->sanitize_url($logo))) {
      $mimetype = str_replace('image/', '', getimagesize($logo)['mime']);
      $this->set_property('PHOTO', vsprintf(\contacts\config::get('PHOTO-BINARY'), array($mimetype, base64_encode(file_get_contents($logo)))));
    }
  }

  /**
   * Add agent. Not currently supported.
   *
   * RFC 2426 pp. 18-19
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.5.4 RFC 2426 Section 3.5.4 (pp. 18-19)
   * @param null $agent Not supported
   */
  public function add_agent($agent) {}

  /**
   * Add organization name to vCard.
   *
   * RFC 2426 p. 19
   *
   * Structured type consisting of the organization name, followed by
   * one or more levels of organizational unit names (semi-colon delimited).
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.5.5 RFC 2426 Section 3.5.5 (p. 19)
   * @param string|array $organization Semi-colon delimited list of organization units (or array)
   * @return null
   */
  public function add_organization($organization) {
    $organization = is_array($organization) ? $organization : explode(';', $organization);
    $this->construct_element('ORG', array($organization), 'semicolon');
  }

  /**
   * Add categories to vCard
   *
   * RFC 2426 pp. 19-20
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.1 RFC 2426 Section 3.6.1 (pp. 19-20)
   * @param string|array $categories Comma-delimited list of categories (or array)
   * @return null
   */
  public function add_categories($categories) {
    $categories = is_array($categories) ? $categories : explode(',', $categories);
    $this->construct_element('CATEGORIES', array($categories));
  }

  /**
   * Add note, supplemental information, or a comment to vCard
   *
   * RFC 2426 p. 20
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.2 RFC 2426 Section 3.6.2 (p. 20)
   * @param string $note Note
   * @return null
   */
  public function add_note($note) {
    $this->construct_element('NOTE', $note);
  }

  /**
   * Add identifier for the product that created the vCard
   *
   * RFC 2426 pp. 20-21
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.3 RFC 2426 Section 3.6.3 (pp. 20-21)
   * @param string $product_id Product ID
   * @return null
   */
  public function add_product_id($product_id) {
    $this->construct_element('PRODID', $product_id);
  }

  /**
   * Add revision date to vCard (For example, `1995-10-31T22:27:10Z`)
   *
   * RFC 2426 p. 21
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.4 RFC 2426 Section 3.6.4 (p. 21)
   * @param null
   * @return null
   */
  public function add_revision() {
    // Set directly rather than going through $this->construct_element to avoid escaping valid timestamp characters
    $this->set_property('REV', vsprintf(\contacts\config::get('REV'), date('Y-m-d\TH:i:s\Z')));
  }

  /**
   * Add sort string to specify the family name or given name text to be used for national-language-specific sorting of the FN and N types
   *
   * RFC 2426 pp. 21-22
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.5 RFC 2426 Section 3.6.5 (pp. 21-22)
   * @param string $sort_string Sort string to use for `FN` and `N`
   * @return null
   */
  public function add_sort_string($sort_string) {
    $this->construct_element('SORT-STRING', $sort_string);
  }

  /**
   * Add sound. Not currently supported.
   *
   * RFC 2426 pp. 22-23
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.6 RFC 2426 Section 3.6.6 (pp. 22-23)
   * @param null $sound Not supported
   */
  public function add_sound($sound) {}

  /**
   * Add a globally unique identifier corresponding to the individual to the vCard
   *
   * RFC 2426 p. 23
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.7 RFC 2426 Section 3.6.7 (p. 23)
   * @param string $unique_identifier Unique identifier
   * @return null
   */
  public function add_unique_identifier($unique_identifier) {
    $this->construct_element('UID', $unique_identifier);
  }

  /**
   * Add uniform resource locator (URL) to vCard
   *
   * RFC 2426 p. 24
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.6.8 RFC 2426 Section 3.6.8 (p. 24)
   * @param string $url URL
   * @return null
   */
  public function add_url($url) {
    // Set directly rather than going through $this->construct_element to avoid escaping valid URL characters
    $this->set_property('URL', vsprintf(\contacts\config::get('URL'), $this->sanitize_url($url)));
  }

  /**
   * Add access classification to vCard
   *
   * RFC 2426 p. 25
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.7.1 RFC 2426 Section 3.7.1 (p. 25)
   * @param string $classification Access classification. Default: `PUBLIC`
   *   * Valid classifications:
   *     * PUBLIC
   *     * PRIVATE
   *     * CONFIDENTIAL
   * @return null
   */
  public function add_classification($classification = null) {
    $classification = $this->in_array_all([$classification], $this->valid_classifications) ? $classification : 'PUBLIC';
    $this->construct_element('CLASS', $classification);
  }

  /**
   * Add key. Not currently supported.
   *
   * RFC 2426 pp. 25-26
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.7.2 RFC 2426 Section 3.7.2 (pp. 25-26)
   * @param null $key Not supported
   */
  public function add_key($key) {}

  /**
   * Add custom extended type to vCard
   *
   * RFC 2426 p. 26
   *
   * @link https://tools.ietf.org/html/rfc2426#section-3.8 RFC 2426 Section 3.8 (p. 26)
   * @param string $label Label for custom extended type
   * @param string $value Value of custom extended type
   * @return null
   */
  public function add_extended_type($label, $value) {
    $this->construct_element('X-', array($label, $value));
  }

  /**
   * Add custom iOS anniversary to vCard
   *
   * @param string $anniversary Anniversary date
   * @return null
   */
  public function add_anniversary($anniversary) {
    $anniversary = date('Y-m-d', strtotime($anniversary));
    $this->construct_element('ANNIVERSARY', array($anniversary, $this->extended_item_count));
    $this->extended_item_count++;
  }

  /**
   * Add custom iOS supervisor to vCard
   *
   * @param string $supervisor Supervisor name
   * @return null
   */
  public function add_supervisor($supervisor) {
    $this->construct_element('SUPERVISOR', array($supervisor, $this->extended_item_count));
    $this->extended_item_count++;
  }

  /**
   * Add custom iOS spouse to vCard
   *
   * @param string $spouse Spouse name
   * @return null
   */
  public function add_spouse($spouse) {
    $this->construct_element('SPOUSE', array($spouse, $this->extended_item_count));
    $this->extended_item_count++;
  }

  /**
   * Add custom iOS child to vCard
   *
   * @param string $child Child name
   * @return null
   */
  public function add_child($child) {
    $this->construct_element('CHILD', array($child, $this->extended_item_count));
    $this->extended_item_count++;
  }

  /**
   * Build the vCard
   *
   * @param bool $write Write vCard to file or not. Default: false
   * @param string $filename Name of vCard file. Default: timestamp
   * @return string vCard as a string
   */
  public function build_vcard($write = false, $filename = null) {
    $filename = empty($filename) ? date('Y.m.d.H.i.s') : $filename;
    $this->add_revision();
    $string  = "BEGIN:VCARD\r\n";
    $string .= "VERSION:3.0\r\n";
    foreach ($this->properties as $property) {
      $value = str_replace('\r\n', "\r\n", $property['value']);
      $string .= $this->fold($value . "\r\n");
    }
    $string .= "END:VCARD\r\n\r\n";
    if($write) {
      $this->write_file($filename . '.vcf', $string, true);
    }
    return $string;
  }

  /**
   * Fold vCard text so each line is 75 characters or less
   *
   * RFC 2426 p. 7
   *
   * @link https://tools.ietf.org/html/rfc2426#section-2.6 RFC 2426 Section 2.6 (p. 7)
   * @param string $text Text to fold
   * @return string Folded text
   */
  protected function fold($text) {
    return (strlen($text) <= 75) ? $text : substr(chunk_split($text, 73, "\r\n "), 0, -3);
  }

  /**
   * Construct the element
   *
   * @param string $element Name of the vCard element
   * @param string|array $value Value to construct. If it's an array, make it a list using
   *   the proper `delimiter`
   * @param string $delimiter Delimiter to use for lists given via `$value` array.
   *   Default: `comma`. Any other value is interpreted as semicolon.
   * @return null
   */
  private function construct_element($element, $value, $delimiter = 'comma') {
    $value = is_array($value) ? array_map(array($this, 'clean_string'), $value, array($delimiter)) : $this->clean_string($value);
    $this->set_property($element, vsprintf(\contacts\config::get($element), $value));
  }

  /**
   * Clean a string be escaping `,` and `;` and `:`
   *
   * @param string|array $string String to escape
   * @param string $delimiter Delimiter to create a list from an array. Default: `comma`.
   *   Any other value is interpreted as semicolon.
   * @return string|null Returns cleaned string or `null`
   */
  private function clean_string($string, $delimiter = 'comma') {
    // If it's an array, clean individual strings and return a comma-delimited list of array values
    if(is_array($string)) {
      foreach($string as $key => $value) {
        $string[$key] = $this->clean_string($value);
      }
      return $delimiter == 'comma' ? implode(',', $string) : implode(';', $string);
    }
    $search  = array(',', ';', ':');
    $replace = array('\,', '\;', '\:');
    return empty($string) ? null : str_replace($search, $replace, $string);
  }

  /**
   * Set vCard property
   *
   * @param string $element vCard element to set
   * @param string $value Value to set vCard element to
   * @return null
   */
  private function set_property($element, $value) {
    if(!in_array($element, $this->multiple_properties_allowed) && isset($this->defined_elements[$element])) {
      throw new \Exception('You can only set "' . $element . '" once.');
    }
    // Define that we set this element
    $this->defined_elements[$element] = true;
    // Add property
    $this->properties[] = array(
      'key'   => $element,
      'value' => $value
    );
  }

}

?>
