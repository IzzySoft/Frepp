<?php
/** XML conversions
 * @class xmlconv
 */
class xmlconv {
  /** Read XML from file and convert it to simleXML
   * @class xmlconv
   * @function xml2simple
   * @param string url file/url containing XML
   * @return object simpleXML
   * @see https://lostechies.com/seanbiefeld/2011/10/21/simple-xml-to-json-with-php/
   */
  public function xml2simple($url) {
    $fileContents = file_get_contents($url);
    $fileContents = str_replace(array("\n", "\r", "\t"), '', $fileContents);
    $fileContents = trim(str_replace('"', "'", $fileContents));
    $simpleXml = simplexml_load_string($fileContents);
    return $simpleXml;
  }

  /** Convert XML to JSON
   * @class xmlconv
   * @function xml2json
   * @param string url file/url containing XML
   * @return object json
   */
  public function xml2json($url) {
    $json = json_encode(self::xml2simple($url));
    return $json;
  }

  /** Convert XML to stdClass object
   * @class xmlconv
   * @function xml2obj
   * @param string url file/url containing XML
   * @return object obj
   */
  public function xml2obj($url) {
    return json_decode(self::xml2json($url));
  }
}
?>