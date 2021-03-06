<?php
/**
 * Create a vCard
 *
 * Known issues:
 *   * Date-time values not supported for `BDAY` field (only date values). No plans to implement.
 *   * Text values not supported for `TZ` field (only UTC-offset values). No plans to implement.
 *   * The following vCard elements are not currently supported (no plans to implement):
 *     * AGENT
 *     * SOUND
 *     * KEY
 *
 * Inspired by https://github.com/jeroendesloovere/vcard
 *
 * @author  Jared Howland <contacts@jaredhowland.com>
 * @version 2017-12-12
 * @since   2016-10-05
 *
 */

namespace Contacts;

/**
 * vCard class to create a vCard. Extends `Contacts` and implements `ContactsInterface`
 */
class Vcard extends Contacts implements ContactsInterface
{
    /**
     * @var array $properties Array of properties added to the vCard object
     */
    private $properties;

    /**
     * @var array $multiplePropertiesAllowed Array of properties that can be set more than once
     */
    private $multiplePropertiesAllowed = [
        'EMAIL',
        'ADR',
        'LABEL',
        'TEL',
        'EMAIL',
        'URL',
        'X-',
        'CHILD'];

    /**
     * @var array $validAddressTypes Array of valid address types
     */
    private $validAddressTypes = [
        'dom',
        'intl',
        'postal',
        'parcel',
        'home',
        'work',
        'pref'];

    /**
     * @var array $validTelephoneTypes Array of valid telephone types
     */
    private $validTelephoneTypes = [
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
        'iphone']; // Custom type

    /**
     * @var array $validClassifications Array of valid classification types
     */
    private $validClassifications = [
        'PUBLIC',
        'PRIVATE',
        'CONFIDENTIAL'];

    /**
     * @var int $extendedItemCount Count of custom iOS elements set
     */
    private $extendedItemCount = 1;

    /**
     * @var array $definedElements Array of defined vCard elements added to the vCard object
     */
    private $definedElements;

    /**
     * Construct Vcard Class
     *
     * @param string $dataDirectory   Directory to save vCard(s) to. Default: `/Data/`
     * @param string $defaultAreaCode Default area code to use for phone numbers. Default: `801`
     * @param string $defaultTimeZone Default time zone to use for Vcard revision date and time. Default: `America/Denver`
     *
     * @return void
     */
    public function __construct(string $dataDirectory = null, string $defaultAreaCode = '801', string $defaultTimeZone = 'America/Denver')
    {
        parent::__construct($dataDirectory, $defaultAreaCode, $defaultTimeZone);
    }

    /**
     * Print out properties and define elements to help with debugging
     *
     * @param null
     *
     * @return string
     */
    public function debug(): string
    {
        $properties = print_r($this->properties);
        $definedElements = print_r($this->definedElements);
        $message = "<pre>**PROPERTIES**\n".$properties."\n\n**DEFINED ELEMENTS**\n".$definedElements;

        return $message;
    }

    /**
     * Get defined properties array
     *
     * @param null
     *
     * @return array Array of defined properties
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Get defined elements array
     *
     * @param null
     *
     * @return array Array of defined elements
     */
    public function getDefinedElements()
    {
        return $this->definedElements;
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
     *
     * @param string $name Full name
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addFullName(string $name)
    {
        $this->constructElement('FN', $name);
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
     *
     * @param string $lastName        Family name
     * @param string $firstName       Given name. Default: `null`
     * @param string $additionalNames Middle name(s). Comma-delimited. Default: `null`
     * @param string $prefixes        Honorific prefix(es). Comma-delimited. Default: `null`
     * @param string $suffixes        Honorific suffix(es). Comma-delimited. Default: `null`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addName(
        string $lastName,
        string $firstName = null,
        string $additionalNames = null,
        string $prefixes = null,
        string $suffixes = null
    ) {
        $additionalNames = str_replace(' ', '', $additionalNames);
        $prefixes = str_replace(' ', '', $prefixes);
        $suffixes = str_replace(' ', '', $suffixes);
        // Set directly rather than going through $this->constructElement to avoid escaping valid commas in `$additionalNames`, `$prefixes`, and `$suffixes`
        $this->setProperty('N', vsprintf(Config::get('N'), [$lastName, $firstName, $additionalNames, $prefixes, $suffixes]));
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
     *
     * @param array $names Nickname(s). Array of nicknames
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addNicknames(array $names)
    {
        $this->constructElement('NICKNAME', [$names]);
    }

    /**
     * Add photo
     *
     * RFC 2426 pp. 9-10
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.1.4 RFC 2426 Section 3.1.4 (pp. 9-10)
     *
     * @param string $photo URL-referenced or base-64 encoded photo
     * @param bool   $isUrl Optional. Is it a URL-referenced photo or a base-64 encoded photo? Default: `true`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addPhoto(string $photo, bool $isUrl = true)
    {
        $this->photoProperty('PHOTO', $photo, $isUrl);
    }

    /**
     * Add birthday to vCard
     *
     * RFC 2426 p. 10
     *
     * Standard allows for date-time values. Not supported in this class.
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.1.5 RFC 2426 Section 3.1.5 (p. 10)
     *
     * @param int $year  Year of birth. If no year given, use iOS custom date field to indicate birth month and day
     *                   only. Default: `null`
     * @param int $month Month of birth.
     * @param int $day   Day of birth.
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addBirthday(int $year = null, int $month, int $day)
    {
        if ($year !== null) {
            $this->constructElement('BDAY', [$year, $month, $day]);
        } else {
            $this->definedElements['BDAY'] = true; // Define `BDAY` element
            $this->constructElement('BDAY-NO-YEAR', [$month, $day]);
        }
    }

    /**
     * Add address to vCard
     *
     * RFC 2426 pp. 10–11
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.2.1 RFC 2426 Section 3.2.1 (pp. 10-11)
     *
     * @param string $poBox    Post office box number
     * @param string $extended Extended address
     * @param string $street   Street address
     * @param string $city     City
     * @param string $state    State/province
     * @param string $zip      Postal code
     * @param string $country  Country
     * @param array  $types    Array of address types
     *                         * Valid `$types`s:
     *                         * `dom` - domestic delivery address
     *                         * `intl` - international delivery address
     *                         * `postal` - postal delivery address
     *                         * `parcel` - parcel delivery address
     *                         * `home` - residence delivery address
     *                         * `work` - work delivery address
     *                         * `pref` - preferred delivery address when more than one address is specified
     *                         * Default: `intl,postal,parcel,work`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addAddress(
        string $poBox = null,
        string $extended = null,
        string $street = null,
        string $city = null,
        string $state = null,
        string $zip = null,
        string $country = null,
        array $types = ['intl', 'postal', 'parcel', 'work']
    ) {
        // Make sure all `$types`s are valid. If invalid `$types`(s), revert to standard default.
        if ($this->inArrayAll($types, $this->validAddressTypes)) {
            $this->constructElement('ADR', [$types, $poBox, $extended, $street, $city, $state, $zip, $country]);
        } else {
            throw new ContactsException("Invalid address type(s): '$types'");
        }
    }

    /**
     * Add mailing label to vCard
     *
     * RFC 2426 p. 12
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.2.2 RFC 2426 Section 3.2.2 (p. 12)
     *
     * @param string $label Mailing label
     * @param array  $types Array of mailing label types
     *                      * Valid `$types`s:
     *                      * `dom` - domestic delivery address
     *                      * `intl` - international delivery address
     *                      * `postal` - postal delivery address
     *                      * `parcel` - parcel delivery address
     *                      * `home` - residence delivery address
     *                      * `work` - work delivery address
     *                      * `pref` - preferred delivery address when more than one address is specified
     *                      * Default: `intl,postal,parcel,work`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addLabel(string $label, array $types = null)
    {
        // Make sure all `$types`s are valid. If invalid `$types`(s), revert to standard default.
        $types = $this->inArrayAll($types, $this->validAddressTypes) ? $types : ['intl', 'postal', 'parcel', 'work'];
        $this->constructElement('LABEL', [$types, $label]);
    }

    /**
     * Add telephone number to vCard
     *
     * RFC 2426 p. 13
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.3.1 RFC 2426 Section 3.3.1 (p. 13)
     *
     * @param string $phone Phone number
     * @param array  $types Array of telephone types
     *                      * Valid `$types`s:
     *                      * `home` - telephone number associated with a residence
     *                      * `msg` - telephone number has voice messaging support
     *                      * `work` - telephone number associated with a place of work
     *                      * `pref` - preferred-use telephone number
     *                      * `voice` - voice telephone number
     *                      * `fax` - facsimile telephone number
     *                      * `cell` - cellular telephone number
     *                      * `video` - video conferencing telephone number
     *                      * `pager` - paging device telephone number
     *                      * `bbs` - bulletin board system telephone number
     *                      * `modem` - MODEM connected telephone number
     *                      * `car` - car-phone telephone number
     *                      * `isdn` - ISDN service telephone number
     *                      * `pcs` - personal communication services telephone number
     *                      * `iphone` - Non-standard type to indicate phone is an iPhone
     *                      * Default: `voice`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addTelephone(string $phone = null, array $types = null)
    {
        // Make sure all `$types`s are valid. If invalid `$types`(s), revert to standard default.
        $types = $this->inArrayAll($types, $this->validTelephoneTypes) ? $types : ['voice'];
        $phone = $this->sanitizePhone($phone);
        if (!empty($phone)) {
            $this->constructElement('TEL', [$types, $phone]);
        }
    }

    /**
     * Add email address to vCard
     *
     * RFC 2426 p. 14
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.3.2 RFC 2426 Section 3.3.2 (p. 14)
     *
     * @param string $email Email address
     * @param array  $types  Array of email address types
     *                      * Valid `$types`s:
     *                      * `internet` - Internet addressing type
     *                      * `x400` - X.400 addressing type
     *                      * `pref` - preferred-use email address when more than one is specified
     *                      * Another IANA registered address type can also be specified
     *                      * A non-standard value can also be specified
     *                      * Default: `internet`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addEmail(string $email = null, array $types = null)
    {
        $types = empty($types) ? ['internet'] : $types;
        $email = $this->sanitizeEmail($email);
        if (!empty($email)) {
            $this->constructElement('EMAIL', [$types, $email]);
        }
    }

    /**
     * Add email software to vCard
     *
     * RFC 2426 pp. 14-15
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.3.3 RFC 2426 Section 3.3.3 (pp. 14-15)
     *
     * @param string $mailer Software used by recipient to send/receive email
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addMailer(string $mailer)
    {
        $this->constructElement('MAILER', $mailer);
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
     *
     * @param string $timeZone Time zone (UTC-offset) as a number between -14 and +12 (inclusive).
     *                         Examples: `-7`, `-12`, `-12:00`, `10:30`
     *                         Invalid time zone values return `+00:00`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addTimeZone(string $timeZone)
    {
        if ($this->sanitizeTimeZone($timeZone)) {
            $this->constructElement('TZ', $this->sanitizeTimeZone($timeZone));
        }
    }

    /**
     * Add latitude and longitude to vCard
     *
     * RFC 2426 pp. 15-16
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.4.2 RFC 2426 Section 3.4.2 (pp. 15-16)
     *
     * @param string $lat  Geographic Positioning System latitude (decimal) (must be a number between -90 and 90)
     *
     * **FORMULA**: decimal = degrees + minutes/60 + seconds/3600
     * @param string $long Geographic Positioning System longitude (decimal) (must be a number between -180 and 180)
     *
     * **FORMULA**: decimal = degrees + minutes/60 + seconds/3600
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addLatLong(string $lat, string $long)
    {
        if ($this->sanitizeLatLong($lat, $long)) {
            $this->constructElement('GEO', $this->sanitizeLatLong($lat, $long));
        }
    }

    /**
     * Add job title to vCard
     *
     * RFC 2426 pp. 16-17
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.5.1 RFC 2426 Section 3.5.1 (pp. 16-17)
     *
     * @param string $title Job title
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addTitle(string $title)
    {
        $this->constructElement('TITLE', $title);
    }

    /**
     * Add role, occupation, or business category to vCard
     *
     * RFC 2426 p. 17
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.5.2 RFC 2426 Section 3.5.2 (p. 17)
     *
     * @param string $role Job role
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addRole(string $role)
    {
        $this->constructElement('ROLE', $role);
    }

    /**
     * Add logo
     *
     * RFC 2426 pp. 17-18
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.5.3 RFC 2426 Section 3.5.3 (pp. 17-18)
     *
     * @param string $logo  URL-referenced or base-64 encoded photo
     * @param bool   $isUrl Optional. Is it a URL-referenced photo or a base-64 encoded photo? Default: `true`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addLogo(string $logo, bool $isUrl = true)
    {
        $this->photoProperty('LOGO', $logo, $isUrl);
    }

    /**
     * Add photo to `PHOTO` or `LOGO` elements
     *
     * @param string $element Element to add photo to
     * @param string $photo   URL-referenced or base-64 encoded photo
     * @param bool   $isUrl   Optional. Is it a URL-referenced photo or a base-64 encoded photo? Default: `true`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    private function photoProperty(string $element, string $photo, bool $isUrl = true)
    {
        if ($isUrl) {
            // Set directly rather than going through $this->constructElement to avoid escaping valid URL characters
            if (!empty($this->sanitizeUrl($photo))) {
                $mimetype = strtoupper(str_replace('image/', '', getimagesize($photo)['mime']));
                $photo = $this->getData($this->sanitizeUrl($photo));
                $this->setProperty($element, vsprintf(Config::get('PHOTO-BINARY'), [$mimetype, base64_encode($photo)]));
            }
        } else {
            $img = base64_decode($photo);
            if (!empty($img)) {
                $file = finfo_open();
                $mimetype = finfo_buffer($file, $img, FILEINFO_MIME_TYPE);
                $mimetype = strtoupper(str_replace('image/', '', $mimetype));
                $this->setProperty($element, vsprintf(Config::get('PHOTO-BINARY'), [$mimetype, $photo]));
            }
        }
    }

    /**
     * Add agent. Not currently supported.
     *
     * RFC 2426 pp. 18-19
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.5.4 RFC 2426 Section 3.5.4 (pp. 18-19)
     *
     * @param string $agent Not implemented
     *
     * @throws ContactsException if this unsupported method is called
     *
     * @return void
     */
    public function addAgent(string $agent = null)
    {
        throw new ContactsException('"AGENT" is not a currently supported element.');
    }

    /**
     * Add organization name to vCard.
     *
     * RFC 2426 p. 19
     *
     * Structured type consisting of the organization name, followed by
     * one or more levels of organizational unit names (semi-colon delimited).
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.5.5 RFC 2426 Section 3.5.5 (p. 19)
     *
     * @param array $organizations Array of organization units
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addOrganizations(array $organizations)
    {
        $this->constructElement('ORG', [$organizations], ';');
    }

    /**
     * Add categories to vCard
     *
     * RFC 2426 pp. 19-20
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.1 RFC 2426 Section 3.6.1 (pp. 19-20)
     *
     * @param array $categories Array of categories
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addCategories(array $categories)
    {
        $this->constructElement('CATEGORIES', [$categories]);
    }

    /**
     * Add note, supplemental information, or a comment to vCard
     *
     * RFC 2426 p. 20
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.2 RFC 2426 Section 3.6.2 (p. 20)
     *
     * @param string $note Note
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addNote(string $note)
    {
        $this->constructElement('NOTE', $note);
    }

    /**
     * Add identifier for the product that created the vCard
     *
     * RFC 2426 pp. 20-21
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.3 RFC 2426 Section 3.6.3 (pp. 20-21)
     *
     * @param string $productId Product ID
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addProductId(string $productId)
    {
        $this->constructElement('PRODID', $productId);
    }

    /**
     * Add revision date to vCard (For example, `1995-10-31T22:27:10Z`)
     *
     * RFC 2426 p. 21
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.4 RFC 2426 Section 3.6.4 (p. 21)
     *
     * @param string $dateTime Date and time to add to card as the revision time. Default: `creation timestamp`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addRevision(string $dateTime = null)
    {
        $dateTime = is_null($dateTime) ? date('Y-m-d\TH:i:s\Z') : date("Y-m-d\TH:i:s\Z", /** @scrutinizer ignore-type */ strtotime($dateTime));
        // Set directly rather than going through $this->constructElement to avoid escaping valid timestamp characters
        $this->setProperty('REV', vsprintf(Config::get('REV'), [$dateTime]));
    }

    /**
     * Add sort string to specify the family name or given name text to be used for national-language-specific sorting
     * of the FN and N types
     *
     * RFC 2426 pp. 21-22
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.5 RFC 2426 Section 3.6.5 (pp. 21-22)
     *
     * @param string $sortString Sort string to use for `FN` and `N`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addSortString(string $sortString)
    {
        $this->constructElement('SORT-STRING', $sortString);
    }

    /**
     * Add sound. Not currently supported.
     *
     * RFC 2426 pp. 22-23
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.6 RFC 2426 Section 3.6.6 (pp. 22-23)
     *
     * @param string $sound Not supported
     *
     * @throws ContactsException if this unsupported method is called
     *
     * @return void
     */
    public function addSound(string $sound = null)
    {
        throw new ContactsException('"SOUND" is not a currently supported element.');
    }

    /**
     * Add a globally unique identifier corresponding to the individual to the vCard
     *
     * RFC 2426 p. 23
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.7 RFC 2426 Section 3.6.7 (p. 23)
     *
     * @param string $uniqueIdentifier Unique identifier. Default: `PHP-generated unique identifier`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addUniqueIdentifier(string $uniqueIdentifier = null)
    {
        $uniqueIdentifier = is_null($uniqueIdentifier) ? uniqid('', true) : $uniqueIdentifier;
        $this->constructElement('UID', $uniqueIdentifier);
    }

    /**
     * Add uniform resource locator (URL) to vCard
     *
     * RFC 2426 p. 24
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.6.8 RFC 2426 Section 3.6.8 (p. 24)
     *
     * @param string $url URL
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addUrl(string $url)
    {
        if ($this->sanitizeUrl($url) !== null) {
            // Set directly rather than going through $this->constructElement to avoid escaping valid URL characters
            $this->setProperty('URL', vsprintf(Config::get('URL'), [$this->sanitizeUrl($url)]));
        }
    }

    /**
     * Add access classification to vCard
     *
     * RFC 2426 p. 25
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.7.1 RFC 2426 Section 3.7.1 (p. 25)
     *
     * @param string $classification Access classification. Default: `PUBLIC`
     *                               * Valid classifications:
     *                               * PUBLIC
     *                               * PRIVATE
     *                               * CONFIDENTIAL
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addClassification(string $classification = 'PUBLIC')
    {
        if ($this->inArrayAll([$classification], $this->validClassifications)) {
            $this->constructElement('CLASS', $classification);
        } else {
            throw new ContactsException("Invalid classification: '$classification'");
        }
    }

    /**
     * Add custom extended type to vCard
     *
     * RFC 2426 p. 26
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.8 RFC 2426 Section 3.8 (p. 26)
     *
     * @param string $label Label for custom extended type
     * @param string $value Value of custom extended type
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addExtendedType(string $label, string $value)
    {
        $this->constructElement('X-', [$label, $value]);
    }

    /**
     * Add key. Not currently supported.
     *
     * RFC 2426 pp. 25-26
     *
     * @link https://tools.ietf.org/html/rfc2426#section-3.7.2 RFC 2426 Section 3.7.2 (pp. 25-26)
     *
     * @param string $key Not supported
     *
     * @throws ContactsException if this unsupported method is called
     *
     * @return void
     */
    public function addKey(string $key = null)
    {
        throw new ContactsException('"KEY" is not a currently supported element.');
    }

    /**
     * Add custom iOS anniversary to vCard
     *
     * @param string $anniversary Anniversary date
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addAnniversary(string $anniversary)
    {
        if (is_int(strtotime($anniversary))) {
            $anniversary = date('Y-m-d', strtotime($anniversary));
            $this->constructElement('ANNIVERSARY', [$anniversary, $this->extendedItemCount]);
            $this->extendedItemCount++;
        } else {
            throw new ContactsException("Invalid date for anniversary: '$anniversary'");
        }
    }

    /**
     * Add custom iOS supervisor to vCard
     *
     * @param string $supervisor Supervisor name
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addSupervisor(string $supervisor)
    {
        $this->constructElement('SUPERVISOR', [$supervisor, $this->extendedItemCount]);
        $this->extendedItemCount++;
    }

    /**
     * Add custom iOS spouse to vCard
     *
     * @param string $spouse Spouse name
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addSpouse(string $spouse)
    {
        $this->constructElement('SPOUSE', [$spouse, $this->extendedItemCount]);
        $this->extendedItemCount++;
    }

    /**
     * Add custom iOS child to vCard
     *
     * @param string $child Child name
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    public function addChild(string $child)
    {
        $this->constructElement('CHILD', [$child, $this->extendedItemCount]);
        $this->extendedItemCount++;
    }

    /**
     * Fold vCard text so each line is 75 characters or less
     *
     * RFC 2426 p. 7
     *
     * @link https://tools.ietf.org/html/rfc2426#section-2.6 RFC 2426 Section 2.6 (p. 7)
     *
     * @param string $text Text to fold
     *
     * @return string Folded text
     */
    protected function fold(string $text)
    {
        return (strlen($text) <= 75) ? $text : substr(chunk_split($text, 73, "\r\n "), 0, -3);
    }

    /**
     * Build the vCard
     *
     * @param bool   $write    Write vCard to file or not. Default: `false`
     * @param string $filename Name of vCard file. Default: `timestamp`
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return string vCard as a string
     */
    public function buildVcard(bool $write = false, string $filename = null)
    {
        $filename = empty($filename) ? date('Y.m.d.H.i.s') : $filename;
        if (!isset($this->definedElements['REV'])) {
            $this->addRevision();
        }
        $string = "BEGIN:VCARD\r\n";
        $string .= "VERSION:3.0\r\n";
        foreach ($this->properties as $property) {
            $value = str_replace('\r\n', "\r\n", $property['value']);
            $string .= $this->fold($value."\r\n");
        }
        $string .= "END:VCARD\r\n\r\n";
        if ($write) {
            $this->writeFile(/** @scrutinizer ignore-type */$filename.'.vcf', $string, true);
        }

        return $string;
    }

    /**
     * Construct the element
     *
     * @param string       $element   Name of the vCard element
     * @param string|array $value     Value to construct. If it's an array, make it a list using the proper `delimiter`
     * @param string       $delimiter Delimiter to use for lists given via `$value` array.
     *                                Default: `,`.
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    private function constructElement(string $element, $value, string $delimiter = ',')
    {
        $value = is_array($value) ? array_map([$this, 'cleanString'], $value, [$delimiter]) : $this->cleanString($value);
        $this->setProperty($element, vsprintf(Config::get($element), $value));
    }

    /**
     * Clean a string by escaping `,` and `;` and `:`
     *
     * @param string|array $string    String to escape
     * @param string       $delimiter Delimiter to create a list from an array. Default: `,`.
     *
     * @return string|null Returns cleaned string or `null`
     */
    private function cleanString($string, $delimiter = ',')
    {
        // If it's an array, clean individual strings and return a delimited list of array values
        if (is_array($string)) {
            foreach ($string as $key => $value) {
                $string[$key] = $this->cleanString($value, $delimiter);
            }

            return implode($delimiter, $string);
        }
        $search = array(',', ';', ':');
        $replace = array('\,', '\;', '\:');

        return empty($string) ? null : str_replace($search, $replace, $string);
    }

    /**
     * Set vCard property
     *
     * @param string $element vCard element to set
     * @param string $value   Value to set vCard element to
     *
     * @throws ContactsException if an element that can only be defined once is defined more than once
     *
     * @return void
     */
    private function setProperty(string $element, string $value)
    {
        if (!in_array($element, $this->multiplePropertiesAllowed) && isset($this->definedElements[$element])) {
            throw new ContactsException('You can only set "'.$element.'" once.');
        }
        // Define that we set this element
        $this->definedElements[$element] = true;
        // Add property
        $this->properties[] = array(
            'key' => $element,
            'value' => $value,
        );
    }
}
