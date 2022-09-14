<?php
/*
 * @package		Joomla.Framework
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @component Phoca Plugin
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License version 2 or later;
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin' );


class plgContentPhocaGallery extends JPlugin
{
	var $_plugin_number	= 0;

	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	public function _setPluginNumber() {
		$this->_plugin_number = (int)$this->_plugin_number + 1;
	}

	public function onContentPrepare($context, &$article, &$params, $page = 0) {

		$app 	= Factory::getApplication();
		$view	= $app->input->get('view');
		if ($view == 'tag') { return; }

		if ($context == 'com_finder.indexer'){
			return true;
		}

		// Not an article (plugin is used outside com_content
		if (!isset($article->id)) {
			$article->id = 0;
		}

		// Include Phoca Gallery
        if (!JComponentHelper::isEnabled('com_phocagallery', true)) {
            echo '<div class="alert alert-danger">Phoca Gallery Error: Phoca Gallery component is not installed or not published on your system</div>';
            return;
        }


		$db 			= Factory::getDBO();
		$document		= Factory::getDocument();
		//$component	= 'com_phocaphoto';
		//$paramsC		= JComponentHelper::getParams($component) ;
		//$param		= (int)$this->params->get( 'medium_image_width', 100 );
		$detail_window 	= $this->params->get('detail_window', 1);
		$display_title 	= $this->params->get('display_title', 1);

		// Start Plugin
		$regex_one		= '/({phocagallery\s*)(.*?)(})/si';
		$regex_all		= '/{phocagallery\s*.*?}/si';
		$matches 		= array();
		$count_matches	= preg_match_all($regex_all,$article->text,$matches,PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);

		$lang = Factory::getLanguage();
		$lang->load('com_phocagallery');


	// Start if count_matches
	if ($count_matches != 0) {

		HtmlHelper::stylesheet( 'media/plg_content_phocagallery/css/phocagallery.css' );


        if (!class_exists('PhocaGalleryLoader')) {
            require_once( JPATH_ADMINISTRATOR.'/components/com_phocagallery/libraries/loader.php');
        }

        phocagalleryimport('phocagallery.path.path');
        phocagalleryimport('phocagallery.file.filethumbnail');
		phocagalleryimport('phocagallery.render.renderdetailwindow');

		$paramsC			= ComponentHelper::getParams('com_phocagallery');
		$large_image_width	= (int)$paramsC->get( 'large_image_width', 640 );
		$large_image_height	= (int)$paramsC->get( 'large_image_height', 480 );

		for($i = 0; $i < $count_matches; $i++) {

			$o = '';
			$this->_setPluginNumber();

			// Plugin variables
			$view 	= '';
			$id		= 0;
			$max	= 0;

			// Get plugin parameters
			$phocagallery	= $matches[0][$i][0];
			preg_match($regex_one,$phocagallery,$phocagallery_parts);
			$parts			= explode("|", $phocagallery_parts[2]);
			$values_replace = array ("/^'/", "/'$/", "/^&#39;/", "/&#39;$/", "/<br \/>/");

			foreach($parts as $key => $value) {

				$values = explode("=", $value, 2);

				foreach ($values_replace as $key2 => $values2) {
					$values = preg_replace($values2, '', $values);
				}

				// Get plugin parameters from article
					 if($values[0]=='view')				{$view					= $values[1];}
				else if($values[0]=='id')				{$id					= $values[1];}
				else if($values[0]=='categoryid')		{$id					= $values[1];}// Backward compatibility - categoryid is alias for id
				else if($values[0]=='max')				{$max					= $values[1];}
			}

			$limit = '';
			$where = '';
			if ($view == 'category') {
				if ($max > 0) {
					$limit 	= ' LIMIT 0,'.(int)$max;
				}
				$where = ' AND a.catid = '.(int)$id;
			} else if ($view == 'image') {
				$where = ' AND a.id ='.(int)$id;
			} else {
                if ($max > 0) {
					$limit 	= ' LIMIT 0,'.(int)$max;
				}
            }


			$query = 'SELECT a.id, a.catid, a.title, a.alias, a.filename, a.description, a.extm, a.exts, a.extw, a.exth, a.extid, a.extl, a.exto'
				. ' FROM #__phocagallery AS a'
				. ' WHERE a.published = 1'
				. ' AND a.approved = 1'
				. $where
				. ' ORDER BY a.ordering'
				. $limit;

				$db->setQuery($query);
				$images = $db->loadObjectList();

				/*if ($i == 0) {
					// First instance - start the block but do not end it until last instance is rendered
					$o .= '<div id="pg-msnr-container" class="pg-photoswipe pg-msnr-container" itemscope itemtype="http://schema.org/ImageGallery">';
				}*/

				if (!empty($images)) {

					//require_once( JPATH_ADMINISTRATOR.'/components/com_phocaphoto/helpers/phocaphoto.php' );
					//$path = PhocaPhotoHelper::getPath();

					if ((int)$this->_plugin_number < 2) {
						HtmlHelper::_('jquery.framework', false);

						/*if ($detail_window == 2) {
							HtmlHelper::stylesheet( 'media/com_phocaphoto/js/prettyphoto/css/prettyPhoto.css' );
							$document->addScript(JURI::root(true).'/media/com_phocaphoto/js/prettyphoto/js/jquery.prettyPhoto.js');

							$js = "\n". 'jQuery(document).ready(function(){
								jQuery("a[rel^=\'prettyPhoto\']").prettyPhoto({'."\n";
							$js .= '  \'social_tools\': 0'."\n";
							$js .= '  });
							});'."\n";
							$document->addScriptDeclaration($js);
						} else {*/

							$document->addStyleSheet(Uri::root(true).'/media/com_phocagallery/js/photoswipe/css/photoswipe.css');
							$document->addStyleSheet(Uri::root(true).'/media/com_phocagallery/js/photoswipe/css/default-skin/default-skin.css');
							$document->addStyleSheet(Uri::root(true).'/media/com_phocagallery/js/photoswipe/css/photoswipe-style.css');
						/*}*/
					}

					/*$nc = (int)$columns_cats;
					$nw = 3;
					if ($nc > 0) {
						$nw = 12/$nc;//1,2,3,4,6,12
					}
				*/
					$count = 0;
					if (count($images) > 1) {
						$count = 1;
					}

					/*$o .= '<div class="row">';*/

					$o .= '<div id="pg-msnr-container-a'.(int)$article->id . '-p'. (int)$this->_plugin_number.'" class="pg-photoswipe pg-msnr-container" itemscope itemtype="http://schema.org/ImageGallery">';

					$o .= '<div class="ph-gallery-plugin-container">';
					///* id="pg-msnr-container-a'.(int)$article->id . '-p'. (int)$this->_plugin_number.'"

					$class = '';
					if ($display_title == 1) {
						$class = ' ph-incl-title';
					}

					foreach ($images as $k => $v) {


						$o .= '<div class="ph-gallery-plugin-box'.$class.'">';
						$o .= '<div class="ph-gallery-plugin-image-container">';
						$o .= '<div class="ph-gallery-plugin-image-box">';

					/*	if ($count == 1) {
							$o .= '<div class="col-sm-6 col-md-'.$nw.'">';
							$o .= '<div class="thumbnail ph-thumbnail">';
						} else {
							$o .= '<div class="ph-thumbnail-one">';
						}*/

						$image = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'medium');


						if ($v->extm != '') {
							$imageM	= $v->extm;
							$imageL	= $v->extl;
						} else {
							$imageMO = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'medium');
							if (isset($imageMO->rel) && $imageMO->rel != '') {
								$imageM = JURI::base(false) .$imageMO->rel;
							}
							$imageLO = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'large');
							if (isset($imageLO->rel) && $imageLO->rel != '') {
								$imageL = JURI::base(false) .$imageLO->rel;
							}
						}

						$o .= '<figure itemprop="associatedMedia" itemscope itemtype="http://schema.org/ImageObject">';
						if ($imageL != '') {
							/*if ($count == 1) {
								$o .= '<a href="'.$imageL.'" rel="prettyPhoto[\'pp_gal_plugin'.(int)$this->_plugin_number.'\']">';
							} else {
								$o .= '<a href="'.$imageL.'" rel="prettyPhoto">';
							}*/

                            // TODO SIZE
							$w = $large_image_width;
							$h = $large_image_height;
							if (isset($v->extw) && $v->extw != '') {
								$extWA = explode(',', $v->extw);
								if (isset($extWA[0])) { $w = $extWA[0];}
							}

							if (isset($v->exth) && $v->exth != '') {
								$extHA = explode(',', $v->exth);
								if (isset($extHA[0])) { $h = $extHA[0];}
							}



							/*if ($detail_window == 2) {

								if ($count == 1) {
									$o .= '<a href="'.$imageL.'" rel="prettyPhoto[\'pp_gal_plugin'.(int)$this->_plugin_number.'\']">';
								} else {
									$o .= '<a href="'.$imageL.'" rel="prettyPhoto">';
								}

							} else {*/
								$o .= '<a class="pg-photoswipe-button" href="'.$imageL.'" itemprop="contentUrl" data-size="'.$w.'x'.$h.'" >';
							/*}*/

						}

						if ($imageM != '') {
							$o .= '<img src="'.$imageM.'" alt="'.$v->title.'" class="c-Image c-Image--shaded" itemprop="thumbnail" />';
						}

						if ($imageL != '') {
							$o .= '</a>';

						}

						if ($display_title == 1) {
							$o .= '<figcaption itemprop="caption description">'. $v->title.'</figcaption>';
						}
						$o .= '</figure>';

						$o .= '</div>'; // end ph-gallery-plugin-image-box

						$o .= '</div>';// end ph-gallery-plugin-image-container
						if ($display_title == 1) {
							$o .= '<div class="ph-gallery-plugin-image-title">'.$v->title.'</div>';
						}

						$o .= '</div>';// end ph-gallery-plugin-box

						/*if ($count == 1) {
							$o .= '</div>'; // end column
						}*/

					}
					//$o .= '</div>';
					$o .= '</div>';// end ph-gallery-plugin-container

					$o .= '</div>';// end pswp

					if ($i == ($count_matches - 1) && $detail_window == 1) {
						// Must be at the end
						$o .= PhocaGalleryRenderDetailWindow::loadPhotoswipeBottom(1,1);
					}
				}

				/*if ($i == ($count_matches - 1)) {
					// Last instance - stop the block here
					$o .= '</div>';
				}*/


				$article->text = preg_replace($regex_all, $o, $article->text, 1);
			}
			return true;
		}
	}
}
?>
