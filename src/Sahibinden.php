<?php

namespace Weblebby;

use Curl\Curl;

class Sahibinden
{
	public $subdomain;

	public function __construct($subdomain = null)
	{
		$this->subdomain = $subdomain;
	}

	public function get(array $queries = [])
	{
		if ( empty($this->subdomain) ) {
			return false;
		}

		if ( isset($queries['sorting']) !== true ) {
			$queries['sorting'] = 'storeShowcase';
		}

		if ( isset($queries['pagingOffset']) !== true || $queries['pagingOffset'] === 1 ) {
			$queries['pagingOffset'] = 0;
		} else if ( $queries['pagingOffset'] === 2 ) {
			$queries['pagingOffset'] = 20;
		} else {
			$queries['pagingOffset'] = $queries['pagingOffset'] * 20 - 20;
		}

		$connection = $this->load("http://{$this->subdomain}.sahibinden.com?" . http_build_query($queries));

		preg_match('#<div class="classified-list" data-total-matches="(.*?)"> <table width="100%">(.*?)</tbody> </table>#', $connection, $parent);

		if ( isset($parent[2]) !== true ) {
			return false;
		}

		preg_match_all('#<tr align="center">(.*?)<td align="left" width="170"> <a href="https://www.sahibinden.com/ilan/(.*?)-(.[0-9]+)/detay" class="classified-image"> <img src="(.*?)" height="120" width="160" alt="(.*?)"(.*?)/> </a> </td> <td align="left"> <a href="https://www.sahibinden.com/ilan/(.*?)/detay" class="description"> (.*?)</a> </td> <td> (.*?) <br /> (.*?)</td>(.*?)<td> <strong class="price"> (.*?)</strong> </td> </tr>#', $parent[2], $posts);

		$posts = $this->array_map($posts, [
			'id' => 3,
			'name' => 8,
			'image' => 4,
			'city' => 9,
			'town' => 10,
			'price' => 12
		]);

		for ($i=0; $i < count($posts); $i++) {
			$price = preg_replace('/[^0-9.,]/', null, $posts[$i]['price']);
			$symbol = str_replace($price, null, $posts[$i]['price']);

			$posts[$i]['price'] = trim(str_replace(['.', ','], [null, '.'], $price));
			$posts[$i]['price_symbol'] = trim($symbol);
			$posts[$i]['name'] = htmlspecialchars_decode($posts[$i]['name'], ENT_QUOTES);
		}

		return $posts;
	}

	public function getAll(array $queries = [])
	{
		$output = [];
		$page_count = $this->pagination($queries) + 1;

		for ($i=1; $i < $page_count; $i++) {
			$queries['pagingOffset'] = $i;
			$posts = $this->get($queries) ?: [];

			foreach ($posts as $key => $value) {
				$output[] = $value;
			}
		}

		return $output;
	}

	public function detail($id)
	{
		if ( empty($this->subdomain) ) {
			return false;
		}

		$connection = $this->load("https://www.sahibinden.com/ilan/{$id}/detay");

		preg_match('#<div class="errorPage404">#', $connection, $error404);

		if ( isset($error404[0]) === true ) {
			return false;
		}

		/*
		 * ID
		 */
		preg_match('#<span class="classifiedId" id="classifiedId">(.*?)</span>#', $connection, $id);

		/*
		 * Name
		 */
		preg_match('#<div class="classifiedDetailTitle"> <h1>(.*?)</h1>#', $connection, $name);

		/*
		 * Description
		 */
		preg_match('#<div id="classifiedDescription" class="uiBoxContainer">(.*?)</div></div><div class="uiBox">#', $connection, $description);

		/*
		 * Images
		 */
		preg_match('#<div class="thumb-imgs-band">(.*?)</div> <div class="(.*?)">#', $connection, $images_parent);
		preg_match_all('#src="https://image5.sahibinden.com/photos/(.*?)/thmb_(.*?).jpg"#', isset($images_parent[1]) ? $images_parent[1] : null, $images);

		$array_images = [];

		for ($i=0; $i < count(isset($images[0]) ? $images[0] : 0); $i++) { 
			$array_images[$i]['thumb'] = 'https://image5.sahibinden.com/photos/' . $images[1][$i] . '/thmb_' . $images[2][$i] . '.jpg';
			$array_images[$i]['image'] = 'https://image5.sahibinden.com/photos/' . $images[1][$i] . '/big_' . $images[2][$i] . '.jpg';
		}

		/*
		 * Price
		 */
		preg_match('#<div class="classifiedInfo "> <h3> (.*?)</h3>#', $connection, $price);

		$price = isset($price[1]) ? strip_tags(str_replace(['.', ','], [null, '.'], $price[1])) : null;

		$price_format = preg_replace('/[^0-9.,]/', null, $price);
		$price_symbol = str_replace([$price_format, 'Emlak Endeksi'], null, $price);

		/*
		 * Address
		 */
		preg_match('#Emlak Endeksi</a> </h3><h2>(.*?)</h2>#', $connection, $address_parent);
		preg_match_all('#<a href="(.*?)"> (.*?)</a>#', isset($address_parent[1]) ? $address_parent[1] : null, $address);

		$address = $this->array_map($address, [
			'name' => 2,
			'url' => 1
		]);

		/*
		 * Options
		 */
		preg_match('#<ul class="classifiedInfoList">(.*?)</ul><p#', $connection, $options_parent);
		preg_match_all('#<li> <strong>(.*?)</strong>&nbsp; <span(.*?)>(.*?)</span> </li>#', isset($options_parent[1]) ? $options_parent[1] : null, $options);

		$options = $this->array_map($options, [
			'key' => [1, null, ['clear_whitespace']],
			'value' => [3, null, ['clear_whitespace']]
		]);

		/*
		 * Company
		 */
		preg_match('#<a href="(.*?)" class="trackLinkClick trackId_logo_magaza"> <img src="(.*?)" alt="(.*?)" /> <span class="storeInfo"> (.*?)</span> </a>#', $connection, $company);

		/*
		 * Company Phones
		 */
		preg_match('#<ul id="phoneInfoPart" class="userContactInfo">(.*?)</ul>#', $connection, $company_phones_parent);
		preg_match_all('#<li> <strong(.*?)>(.*?)</strong> <span class="pretty-phone-part">(.*?)</span>(.*?)</li>#', isset($company_phones_parent[1]) ? $company_phones_parent[1] : null, $company_phones);

		$company_phones = $this->array_map($company_phones, [
			'key' => 2,
			'value' => 3
		]);

		/*
		 * Author
		 */
		preg_match('#<div class="username-info-area"> <h5>(.*?)</h5> </div>#', $connection, $author_name);

		/*
		 * Location
		 */
		preg_match('#<div id="gmap" data-lat="(.*?)" data-lon="(.*?)" data-lang="tr"></div>#', $connection, $location);

		/*
		 * Extra
		 */
		preg_match('#<div class="uiBoxContainer classifiedDescription" id="classifiedProperties">(.*?)</div> </div><script type="text/javascript"> var bannerZoneId#', $connection, $extra_parent);
		
		preg_match_all('#<h3>(.*?)</h3>#', isset($extra_parent[1]) ? $extra_parent[1] : null, $extra_title);
		preg_match_all('#<ul>(.*?)</ul>#', isset($extra_parent[1]) ? $extra_parent[1] : null, $extra_content);

		$extra_array = [];
		$extra_content_count = isset($extra_content[1]) ? count($extra_content[1]) : 0;

		for ($i=0; $i < $extra_content_count; $i++) { 
			preg_match_all('#<li class="selected"> (.*?)</li>#', $extra_content[1][$i], $extra);

			$extra_array[] = [
				'name' => $extra_title[1][$i],
				'options' => $extra[1]
			];
		}

		/*
		 * Video
		 */
		preg_match('#<a id="videoContainer" class="videoContainer" href="(.*?)"></a>#', $connection, $video);

		$info = [
			'id' => isset($id[1]) ? $id[1] : null,
			'name' => isset($name[1]) ? $name[1] : null,
			'description' => isset($description[1]) ? $description[1] : null,
			'author' => isset($author_name[1]) ? $author_name[1] : null,
			'price' => $price_format,
			'price_symbol' => trim($price_symbol),
			'images' => $array_images,
			'video' => isset($video[1]) ? $video[1] : null,
			'options' => $options,
			'address' => $address,
			'location' => [
				'latitude' => isset($location[1]) ? $location[1] : null,
				'longitude' => isset($location[2]) ? $location[2] : null
			],
			'extra' => $extra_array,
			'company' => [
				'name' => isset($company[3]) ? $company[3] : null,
				'logo' => isset($company[2]) ? $company[2] : null,
				'phones' => isset($company_phones) ? $company_phones : null
			]
		];

		return $info;
	}

	public function pagination(array $queries = [])
	{
		if ( empty($this->subdomain) ) {
			return false;
		}

		return ceil($this->meta($queries, 'post_count') / 20);
	}

	public function meta(array $queries = [], $index = null)
	{
		if ( empty($this->subdomain) ) {
			return false;
		}

		$connection = $this->load("http://{$this->subdomain}.sahibinden.com?" . http_build_query($queries));

		preg_match('#<div class="classified-count"> <span>İLAN SAYISI</span> <strong> (.*?)</strong> </div>#', $connection, $total_post);
		preg_match('#<a href="(.*?)" class="top-category"> <img src="(.*?)" height="70" width="190 " alt="(.*?)"/>#', $connection, $logo);
		preg_match('#<div class="about"> <h4>Hakkımızda</h4> <h2> (.*?)</h2> </div>#', $connection, $description);
		
		preg_match('#<h1>(.*?)</h1> (.*?) </div> <!-- info -->#', $connection, $cellphones_parent);
		preg_match_all('#<p>(.*?)</p>#', isset($cellphones_parent[2]) ? $cellphones_parent[2] : null, $cellphones);

		$meta = [
			'name' => isset($logo[3]) ? $logo[3] : null,
			'description' => isset($description[1]) ? $description[1] : null,
			'logo' => isset($logo[2]) ? $logo[2] : null,
			'post_count' => isset($total_post[1]) ? $total_post[1] : null,
			'cellphones' => isset($cellphones[1]) ? $cellphones[1] : null
		];

		if ( $index === null ) {
			return $meta;
		}

		if ( isset($meta[$index]) === true ) {
			return $meta[$index];
		}

		return false;
	}

	public function html()
	{
		if ( empty($this->subdomain) ) {
			return false;
		}

		return $this->load("http://{$this->subdomain}.sahibinden.com");
	}

	private function array_map($array, $indexes)
	{
		$output = [];

		foreach ($indexes as $key => $value) {
			for ($i=0; $i < count($array[0]); $i++) {
				$output[$i][$key] = isset($value[0]) ? $array[$value[0]][$i] : $array[$value][$i];

				if ( isset($value[1]) && $value[1] !== null ) {
					$output[$i][$key] = str_replace(':value', $array[$value[0]][$i], $value[1]);
				}

				if ( is_array($value[2]) !== true || isset($value[2]) !== true ) {
					continue;
				}

				if ( in_array('clear_whitespace', $value[2]) ) {
					$output[$i][$key] = preg_replace('/\s+/', ' ', trim($output[$i][$key]));
				}
			}
		}

		return $output;
	}

	private function load($url)
	{
		$curl = new Curl();
		
		$curl->setOpt(CURLOPT_FOLLOWLOCATION, true);

		$connection = $curl->get($url);
		$connection = str_replace(["\n", "\r", "\t"], null, $connection);
		$connection = preg_replace('/\s+/', ' ', $connection);

		return $connection;
	}
}