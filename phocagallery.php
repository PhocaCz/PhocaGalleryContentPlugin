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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');


class plgContentPhocaGallery extends JPlugin
{
    public $_plugin_number = 0;
    public $_plugin_number_category_view = 0;

    public function __construct(&$subject, $config) {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    public function _setPluginNumber() {
        $this->_plugin_number = (int)$this->_plugin_number + 1;

    }

    public function _setPluginNumberCategoryView() {
        $this->_plugin_number_category_view = (int)$this->_plugin_number_category_view + 1;
    }

    public function onContentPrepare($context, &$article, &$params, $page = 0) {

        $app  = Factory::getApplication();
        $view = $app->input->get('view');

        if ($view == 'tag') {
            return;
        }

        if ($context == 'com_finder.indexer') {
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


        $db       = Factory::getDBO();
        $document = Factory::getDocument();
        $user		= Factory::getUser();
        //$component	= 'com_phocaphoto';
        //$paramsC		= JComponentHelper::getParams($component) ;
        //$param		= (int)$this->params->get( 'medium_image_width', 100 );
        $detail_window = $this->params->get('detail_window', 1);
        $display_title = $this->params->get('display_title', 1);
        $photoswipe_display_caption = $this->params->get('photoswipe_display_caption', 1);

        // Start Plugin
        $regex_one     = '/({phocagallery\s*)(.*?)(})/si';
        $regex_all     = '/{phocagallery\s*.*?}/si';
        $matches       = array();
        $count_matches = preg_match_all($regex_all, $article->text, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);

        $lang = Factory::getLanguage();
        $lang->load('com_phocagallery');


        // Start if count_matches
        if ($count_matches != 0) {

            //HtmlHelper::stylesheet('media/plg_content_phocagallery/css/phocagallery.css');


            if (!class_exists('PhocaGalleryLoader')) {
                require_once(JPATH_ADMINISTRATOR . '/components/com_phocagallery/libraries/loader.php');
            }

            phocagalleryimport('phocagallery.path.path');
            phocagalleryimport('phocagallery.file.filethumbnail');
            phocagalleryimport('phocagallery.render.renderdetailwindow');
            phocagalleryimport('phocagallery.render.renderfront');
            phocagalleryimport('phocagallery.file.file');
            phocagalleryimport('phocagallery.category.category');
            phocagalleryimport('phocagallery.html.categoryhtml');
            phocagalleryimport('phocagallery.access.access');
            phocagalleryimport('phocagallery.image.imagefront');
            phocagalleryimport('phocagallery.path.route');
            phocagalleryimport('phocagallery.ordering.ordering');

            $paramsC            = ComponentHelper::getParams('com_phocagallery');
            $large_image_width  = (int)$paramsC->get('large_image_width', 640);
            $large_image_height = (int)$paramsC->get('large_image_height', 480);
            $medium_image_width = $paramsC->get( 'medium_image_width', 256 );
		    $medium_image_height= $paramsC->get( 'medium_image_height', 192 );
            $categories_image_ordering		= $paramsC->get( 'categories_image_ordering', 10 );

            // Categories
            $hide_categories		= $paramsC->get( 'hide_categories', '');

            for ($i = 0; $i < $count_matches; $i++) {

                $o = '';
                $this->_setPluginNumber();

                // Plugin variables
                $view = '';
                $id   = 0;
                $category_alias   = '';
                $max  = 0;
                $imageid				= 0;
                $limitstart = 0;
                $limitcount = 0;
                $imageordering = 1;

                $image_categories_size			= 'medium';
                $img_cat				           = 1;

                // Get plugin parameters
                $phocagallery = $matches[0][$i][0];
                preg_match($regex_one, $phocagallery, $phocagallery_parts);
                $parts          = explode("|", $phocagallery_parts[2]);
                $values_replace = array("/^'/", "/'$/", "/^&#39;/", "/&#39;$/", "/<br \/>/");

                foreach ($parts as $key => $value) {

                    $values = explode("=", $value, 2);

                    foreach ($values_replace as $key2 => $values2) {
                        $values = preg_replace($values2, '', $values);
                    }

                    // Get plugin parameters from article
                    if ($values[0] == 'view') {                 $view = $values[1];}
                    else if ($values[0] == 'id') {              $id = $values[1];}
                    else if ($values[0] == 'categoryid') {      $id = $values[1];}// Backward compatibility - categoryid is alias for id
                    else if ($values[0] == 'alias') {           $category_alias = $values[1];}
                    else if ($values[0]=='imageid')			{$imageid				= $values[1];}
                    else if ($values[0] == 'max') {             $max = $values[1];}
                    else if ($values[0] == 'limitstart') {             $limitstart = $values[1];}
                    else if ($values[0] == 'limitcount') {             $limitcount = $values[1];}
                    else if ($values[0] == 'imageordering') {             $imageordering = $values[1];}

                    // Categories
                    else if($values[0]=='hidecategories')	{$hide_categories		= $values[1];}
                    else if($values[0]=='imagecategoriessize')	{$image_categories_size			= $values[1];}
                    else if($values[0]=='imagecategories')		{$img_cat				= $values[1];}
                }



                if ((int)$this->_plugin_number < 2) {
                    PhocaGalleryRenderFront::renderAllCSS();
                    $layoutSVG 	= new FileLayout('svg_definitions', null, array('component' => 'com_phocagallery'));

                    // SVG Definitions
                    $d          = array();
                   $o .= $layoutSVG->render($d);

                }


                //--------------------------
                // DISPLAYING OF CATEGORIES (link doesn't work if there is no menu link)
                //--------------------------
                if ($view == 'categories') {

                    $catid        = $id;
                    $hideCat      = trim($hide_categories);
                    $hideCatArray = explode(',', $hideCat);
                    $hideCatSql   = '';
                    if (is_array($hideCatArray)) {
                        foreach ($hideCatArray as $value) {
                            $hideCatSql .= ' AND cc.id != ' . (int)trim($value) . ' ';
                        }
                    }
                    $uniqueCatSql = '';
                    if ($catid > 0) {
                        $uniqueCatSql = ' AND cc.id = ' . $catid . '';
                    }


                    //CATEGORIES
                    $queryc = 'SELECT cc.*, a.catid, COUNT(a.id) AS numlinks,'
                        . ' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(\':\', cc.id, cc.alias) ELSE cc.id END as slug'
                        . ' FROM #__phocagallery_categories AS cc'
                        . ' LEFT JOIN #__phocagallery AS a ON a.catid = cc.id'
                        . ' WHERE a.published = 1'
                        . ' AND cc.published = 1'
                        . ' AND cc.approved = 1'
                        . ' AND a.approved = 1'
                        . ' AND a.language IN (' . $db->Quote(Factory::getLanguage()->getTag()) . ',' . $db->Quote('*') . ')'
                        . ' AND cc.language IN (' . $db->Quote(Factory::getLanguage()->getTag()) . ',' . $db->Quote('*') . ')'
                        . $hideCatSql
                        . $uniqueCatSql
                        . ' GROUP BY cc.id'
                        . ' ORDER BY cc.ordering';

                    //SUBCATEGORIES
                    $querysc = 'SELECT cc.title AS text, cc.id AS value, cc.parent_id as parentid'
                        . ' FROM #__phocagallery_categories AS cc'
                        . ' WHERE cc.published = 1'
                        . ' AND cc.approved = 1'
                        . ' AND cc.language IN (' . $db->Quote(Factory::getLanguage()->getTag()) . ',' . $db->Quote('*') . ')'
                        . ' ORDER BY cc.ordering';


                    $data_outcome_array = array();

                    $db->setQuery($queryc);
                    $outcome_data = $db->loadObjectList();

                    $db->setQuery($querysc);
                    $outcome_subcategories = $db->loadObjectList();

                    $tree = array();
                    $text = '';
                    $tree = PhocaGalleryCategoryhtml::CategoryTreeOption($outcome_subcategories, $tree, 0, $text, -1);

                    foreach ($tree as $key => $value) {
                        foreach ($outcome_data as $key2 => $value2) {
                            if ($value->value == $value2->id) {

                                $data_outcome                    = new stdClass();
                                $data_outcome->id                = $value2->id;
                                $data_outcome->parent_id         = $value2->parent_id;
                                $data_outcome->title             = $value->text;
                                $data_outcome->name              = $value2->name;
                                $data_outcome->alias             = $value2->alias;
                                $data_outcome->image             = $value2->image;
                                $data_outcome->section           = $value2->section;
                                $data_outcome->image_position    = $value2->image_position;
                                $data_outcome->description       = $value2->description;
                                $data_outcome->published         = $value2->published;
                                $data_outcome->editor            = $value2->editor;
                                $data_outcome->ordering          = $value2->ordering;
                                $data_outcome->access            = $value2->access;
                                $data_outcome->accessuserid      = $value2->accessuserid;
                                $data_outcome->uploaduserid      = $value2->uploaduserid;
                                $data_outcome->deleteuserid      = $value2->deleteuserid;
                                $data_outcome->count             = $value2->count;
                                $data_outcome->params            = $value2->params;
                                $data_outcome->catid             = $value2->catid;
                                $data_outcome->numlinks          = $value2->numlinks;
                                $data_outcome->slug              = $value2->slug;
                                $data_outcome->link              = PhocaGalleryRoute::getCategoryRoute($value2->id, $value2->alias);
                                $data_outcome->filename          = '';
                                $data_outcome->linkthumbnailpath = '';
                                $data_outcome->extm              = '';
                                $data_outcome->exts              = '';
                                $data_outcome->extw              = '';
                                $data_outcome->exth              = '';
                                $data_outcome->extid             = '';

                                //FILENAME
                                $queryfn = 'SELECT filename, extm, exts, extw, exth, extid'
                                    . ' FROM #__phocagallery'
                                    . ' WHERE catid=' . $value2->id
                                    . ' AND published = 1'
                                    . ' AND approved = 1'
                                    . ' AND language IN (' . $db->Quote(Factory::getLanguage()->getTag()) . ',' . $db->Quote('*') . ')'
                                    . ' ORDER BY ordering LIMIT 1';
                                $db->setQuery($queryfn);
                                $outcome_filename       = $db->loadObjectList();
                                $data_outcome->filename = $outcome_filename[0]->filename;
                                $data_outcome->extm     = $outcome_filename[0]->extm;
                                $data_outcome->exts     = $outcome_filename[0]->exts;
                                $data_outcome->extw     = $outcome_filename[0]->extw;
                                $data_outcome->exth     = $outcome_filename[0]->exth;
                                $data_outcome->extid    = $outcome_filename[0]->extid;



                                // Display Key Icon (in case we want to display unaccessable categories in list view)
                                $rightDisplayKey  = 1;

                                // we simulate that we want not to display unaccessable categories
                                // so we get rightDisplayKey = 0 then the key will be displayed

                                //$rightDisplayKey = PhocaGalleryAccess::getUserRight ('accessuserid', $category->accessuserid ,$category->access, $user->get('aid', 0), $user->get('id', 0), 0);
                                $rightDisplayKey = PhocaGalleryAccess::getUserRight('accessuserid', $data_outcome->accessuserid, $data_outcome->access, $user->getAuthorisedViewLevels(), $user->get('id', 0), 0);


                                // Is Ext Image Album?
                                if (!isset($data_outcome->extfbcatid)) {$data_outcome->extfbcatid = '';}
                                $extCategory = PhocaGalleryImage::isExtImage($data_outcome->extid, $data_outcome->extfbcatid);

                                if ($extCategory) {


                                    $data_outcome->rightdisplaykey				= $rightDisplayKey;
                                    if ($categories_image_ordering != 10) {
                                        $imagePic		= PhocaGalleryImageFront::getRandomImageRecursive($data_outcome->id,$categories_image_ordering, 1);
                                        if ($rightDisplayKey == 0) {
                                            $imagePic = new StdClass();
                                            $imagePic->exts = '';
                                            $imagePic->extm = '';
                                            $imagePic->extw = '';
                                            $imagePic->exth = '';
                                        }
                                        $fileThumbnail	= PhocaGalleryImageFront::displayCategoriesExtImgOrFolder($imagePic->exts,$imagePic->extm, $imagePic->extw,$imagePic->exth, $image_categories_size, $rightDisplayKey);

                                        if ($rightDisplayKey == 0) {
                                                $data_outcome->rightdisplaykey = 0;// Lock folder will be displayed
                                                $data_outcome->linkthumbnailpath = '';
                                            } else if (!$fileThumbnail) {
                                                $data_outcome->linkthumbnailpath = '';// Standard folder will be displayed
                                            } else {
                                                $data_outcome->linkthumbnailpath	= $fileThumbnail->rel;
                                                $data_outcome->extw				= $fileThumbnail->extw;
                                                $data_outcome->exth				= $fileThumbnail->exth;
                                                $data_outcome->extpic				= $fileThumbnail->extpic;
                                            }

                                    } else {
                                        $fileThumbnail		= PhocaGalleryImageFront::displayCategoriesExtImgOrFolder($data_outcome->exts,$data_outcome->extm, $data_outcome->extw, $data_outcome->exth, $image_categories_size, $rightDisplayKey);

                                        if ($rightDisplayKey == 0) {
                                                $data_outcome->rightdisplaykey = 0;// Lock folder will be displayed
                                                $data_outcome->linkthumbnailpath = '';
                                            } else if (!$fileThumbnail) {
                                                $data_outcome->linkthumbnailpath = '';// Standard folder will be displayed
                                            } else {
                                                $data_outcome->linkthumbnailpath	= $fileThumbnail->rel;
                                                $data_outcome->extw				= $fileThumbnail->extw;
                                                $data_outcome->exth				= $fileThumbnail->exth;
                                                $data_outcome->extpic				= $fileThumbnail->extpic;
                                            }


                                    }

                                } else {

                                    $data_outcome->rightdisplaykey				= $rightDisplayKey;

                                    if (isset($v->image_id) && $v->image_id > 0) {
                                        // User has selected image in category edit
                                        $selectedImg = PhocaGalleryImageFront::setFileNameByImageId((int)$v->image_id);


                                        if (isset($selectedImg->filename) && ($selectedImg->filename != '' && $selectedImg->filename != '-')) {
                                            $fileThumbnail	= PhocaGalleryImageFront::displayCategoriesImageOrFolder($selectedImg->filename, $image_categories_size, $rightDisplayKey);

                                            if ($rightDisplayKey == 0) {
                                                $data_outcome->rightdisplaykey = 0;// Lock folder will be displayed
                                                $data_outcome->linkthumbnailpath = '';
                                            } else if (!$fileThumbnail) {
                                                $data_outcome->linkthumbnailpath = '';// Standard folder will be displayed
                                            } else {
                                                $data_outcome->filename          = $selectedImg->filename;
                                                $data_outcome->linkthumbnailpath = $fileThumbnail->rel;
                                            }


                                        } else if (isset($selectedImg->exts) && isset($selectedImg->extm) && $selectedImg->exts != '' && $selectedImg->extm != '') {
                                            $fileThumbnail		= PhocaGalleryImageFront::displayCategoriesExtImgOrFolder($selectedImg->exts, $selectedImg->extm, $selectedImg->extw, $selectedImg->exth, $image_categories_size, $rightDisplayKey);



                                            if ($rightDisplayKey == 0) {
                                                $data_outcome->rightdisplaykey = 0;// Lock folder will be displayed
                                                $data_outcome->linkthumbnailpath = '';
                                            } else if (!$fileThumbnail) {
                                                $data_outcome->linkthumbnailpath = '';// Standard folder will be displayed
                                            } else {
                                                $data_outcome->linkthumbnailpath	= $fileThumbnail->rel;
                                                $data_outcome->extw				= $fileThumbnail->extw;
                                                $data_outcome->exth				= $fileThumbnail->exth;
                                                $data_outcome->extpic				= $fileThumbnail->extpic;
                                            }

                                        }

                                    } else {

                                        // Standard Internal Image
                                        if ($categories_image_ordering != 10) {
                                            $data_outcome->filename	= PhocaGalleryImageFront::getRandomImageRecursive($data_outcome->id, $categories_image_ordering);
                                        }
                                        $fileThumbnail	= PhocaGalleryImageFront::displayCategoriesImageOrFolder($data_outcome->filename, $image_categories_size, $rightDisplayKey);

                                        if ($rightDisplayKey == 0) {
                                            $data_outcome->rightdisplaykey = 0;// Lock folder will be displayed
                                            $data_outcome->linkthumbnailpath = '';
                                        } else if (!$fileThumbnail) {
                                            $data_outcome->linkthumbnailpath = '';// Standard folder will be displayed
                                        } else {
                                            $data_outcome->linkthumbnailpath = $fileThumbnail->rel;
                                        }



                                    }


                                }


                                $data_outcome_array[] = $data_outcome;
                            }
                        }
                    }

                    $o .= '<div class="pg-categories-items-box">';



                    if ($img_cat == 1) {
                        foreach ($data_outcome_array as $k => $item) {


                            $o .= '<div class="pg-category-box">';

                            if (isset($item->rightdisplaykey) && $item->rightdisplaykey == 0) {

                                $o .= '<div class="pg-category-box-image pg-svg-box">';
                                $o .= '<svg alt="' . htmlspecialchars($item->title) . '" class="ph-si ph-si-lock-medium pg-image c-Image c-Image--shaded" style="width:' . $medium_image_width . 'px;height:' . $medium_image_height . 'px" itemprop="thumbnail"><use xlink:href="#ph-si-lock"></use></svg>';
                                $o .= '</div>';
                            } else {

                                if ($image_categories_size == 2 || $image_categories_size == 3 || $item->linkthumbnailpath == '') {
                                    // Folders instead of icons
                                    $o .= '<div class="pg-category-box-image pg-svg-box">';
                                    $o .= '<a href="' . Route::_($item->link) . '"><svg alt="' . htmlspecialchars($item->title) . '" class="ph-si ph-si-category pg-image c-Image c-Image--shaded" style="width:' . $medium_image_width . 'px;height:' . $medium_image_height . 'px" itemprop="thumbnail"><use xlink:href="#ph-si-category"></use></svg></a>';
                                    $o .= '</div>';
                                } else {
                                    // Images
                                    $o .= '<div class="pg-category-box-image">';
                                    $o .= '<a href="' . Route::_($item->link) . '">' . HTMLHelper::_('image', $item->linkthumbnailpath, $item->title) . '</a>';
                                    $o .= '</div>';
                                }


                            }

                            $o .= '<div class="pg-category-box-info">';
                            $o .= '<div class="pg-category-box-title">';
                            $o .= '<svg class="ph-si ph-si-category"><use xlink:href="#ph-si-category"></use></svg>';
                            $o .= '<a href="' . Route::_($item->link) . '">' . $item->title . '</a>';
                            $o .= $item->numlinks > 0 ? ' <span class="pg-category-box-count">(' . $item->numlinks . ')</span>' : '';
                            $o .= '</div>';


                            /*if ($this->t['display_cat_desc_box'] == 1 && $item->description != '') {
                                $o .= '<div class="pg-category-box-description">' . strip_tags($item->description) . '</div>';
                            } else if ($this->t['display_cat_desc_box'] == 2 && $item->description != '') {
                                $o .= '<div class="pg-category-box-description">' . (HTMLHelper::_('content.prepare', $item->description, 'com_phocagallery.category')) . '</div>';
                            }*/

                            //$this->cv = $item;
                            //$o .= $this->loadTemplate('rating');

                            $o .= '</div>';// pg-category-box-info
                            $o .= '</div>';// pg-category-box
                        }

                    } else {
                        $o .= '<ul>';

                        foreach ($data_outcome_array as $item) {


                            $o .='<li>'
                             .'<a href="'.Route::_($item->link).'" class="category'.$this->params->get( 'pageclass_sfx' ).'">'
                             . $item->title.'</a>&nbsp;<span class="small">('.$item->numlinks.')</span>'
                             .'</li>';
                        }
                        $o .= '</ul>';
                    }

                    $o .= '</div>';
                }


                //--------------------------
                // DISPLAYING OF CATEGORY (link doesn't work if there is no menu link)
                //--------------------------


                if ($view == 'category') {
                    //------- add by zhang, query category's id -------------
                    if (!$category_alias) {
                        $queryc = 'SELECT a.id'
                            . ' FROM #__phocagallery_categories AS a'
                            . ' WHERE a.alias = \'' . $category_alias . '\'';
                        $db->setQuery($queryc);
                        $outcome_data = $db->loadObjectList();
                        if (!empty($outcome_data)) {
                            $id = $outcome_data[0]->id;
                        }
                    }
                    //----------------------------------

                    $this->_setPluginNumberCategoryView();
                    $layoutBI 	= new FileLayout('box_image', null, array('component' => 'com_phocagallery'));


                    $limit = '';
                    $where = '';

                    // Only one image
                    if ((int)$imageid > 0) {
                        $where = ' AND a.id = '. $imageid;
                    }

                    // Max is the limit, if limitcount is smaller than max, use the limitcount
                    if ((int)$limitcount < (int)$max) {
                        $max = $limitcount;
                    }
                    if ((int)$limitcount > 0 && (int)$max == 0) {
                        $max = $limitcount;
                    }

                    if ($view == 'category') {
                        if ($max > 0) {
                            $limit = ' LIMIT '.(int)$limitstart.',' . (int)$max;
                        }
                        $where .= ' AND a.catid = ' . (int)$id;
                    } else if ($view == 'image') {
                        $where .= ' AND a.id =' . (int)$id;
                    } else {
                        if ($max > 0) {
                            $limit = ' LIMIT '.(int)$limitstart.',' . (int)$max;
                        }
                    }


                    $ordering = PhocaGalleryOrdering::getOrderingString($imageordering);


                    $query = 'SELECT a.id, a.catid, a.title, a.alias, a.filename, a.description, a.extm, a.exts, a.extw, a.exth, a.extid, a.extl, a.exto'
                        . ' FROM #__phocagallery AS a'
                        . ' WHERE a.published = 1'
                        . ' AND a.approved = 1'
                        . $where
                        .$ordering['output']
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



                        if ((int)$this->_plugin_number_category_view < 2) {
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

                            //$document->addStyleSheet(Uri::root(true) . '/media/com_phocagallery/js/photoswipe/css/photoswipe.css');
                            //$document->addStyleSheet(Uri::root(true) . '/media/com_phocagallery/js/photoswipe/css/default-skin/default-skin.css');
                            //$document->addStyleSheet(Uri::root(true) . '/media/com_phocagallery/js/photoswipe/css/photoswipe-style.css');

                            $wa = $document->getWebAssetManager();
		                    $wa->registerAndUseStyle('plg_content_phocagallery.photoswipe', 'media/com_phocagallery/js/photoswipe/css/photoswipe.css', array('version' => 'auto'));
                            $wa->registerAndUseStyle('plg_content_phocagallery.photoswipe.default', 'media/com_phocagallery/js/photoswipe/css/default-skin/default-skin.css', array('version' => 'auto'));
                            $wa->registerAndUseStyle('plg_content_phocagallery.photoswipe.style', 'media/com_phocagallery/js/photoswipe/css/photoswipe-style.css', array('version' => 'auto'));


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

                        $o .= '<div id="pg-msnr-container-a' . (int)$article->id . '-p' . (int)$this->_plugin_number_category_view . '" class="pg-photoswipe pg-msnr-container pg-category-items-box" itemscope itemtype="http://schema.org/ImageGallery">';


                        //$o .= '<div class="ph-gallery-plugin-container pg-category-view pg-cv">';
                        ///* id="pg-msnr-container-a'.(int)$article->id . '-p'. (int)$this->_plugin_number.'"

                        $class = '';
                        if ($display_title == 1) {
                            $class = ' ph-incl-title';
                        }

                        foreach ($images as $k => $v) {

                            $o .= '<div class="pg-item-box">'. "\n";// BOX START
                            //$o .= '<div class="ph-gallery-plugin-box' . $class . '">';
                            //$o .= '<div class="ph-gallery-plugin-image-container">';
                            //$o .= '<div class="ph-gallery-plugin-image-box">';

                            /*	if ($count == 1) {
                                    $o .= '<div class="col-sm-6 col-md-'.$nw.'">';
                                    $o .= '<div class="thumbnail ph-thumbnail">';
                                } else {
                                    $o .= '<div class="ph-thumbnail-one">';
                                }*/

                            $image = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'medium');


                            if ($v->extm != '') {
                                $imageM = $v->extm;
                                $imageL = $v->extl;
                            } else {
                                $imageMO = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'medium');
                                if (isset($imageMO->rel) && $imageMO->rel != '') {
                                    $imageM = JURI::base(false) . $imageMO->rel;
                                }
                                $imageLO = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'large');
                                if (isset($imageLO->rel) && $imageLO->rel != '') {
                                    $imageL = JURI::base(false) . $imageLO->rel;
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
                                    if (isset($extWA[0])) {
                                        $w = $extWA[0];
                                    }
                                }

                                if (isset($v->exth) && $v->exth != '') {
                                    $extHA = explode(',', $v->exth);
                                    if (isset($extHA[0])) {
                                        $h = $extHA[0];
                                    }
                                }


                                /*if ($detail_window == 2) {

                                    if ($count == 1) {
                                        $o .= '<a href="'.$imageL.'" rel="prettyPhoto[\'pp_gal_plugin'.(int)$this->_plugin_number.'\']">';
                                    } else {
                                        $o .= '<a href="'.$imageL.'" rel="prettyPhoto">';
                                    }

                                } else {*/
                                ///$o .= '<a class="pg-photoswipe-button" href="' . $imageL . '" itemprop="contentUrl" data-size="' . $w . 'x' . $h . '" >';
                                /*}*/
                                $v->datasize = 'data-size="' . $w . 'x' . $h . '"';

                            }

                         /*   if ($imageM != '') {
                                $o .= '<img src="' . $imageM . '" alt="' . $v->title . '" class="c-Image c-Image--shaded" itemprop="thumbnail" />';
                            }

                            if ($imageL != '') {
                                $o .= '</a>';

                            }*/

                            // Display BOX IMAGE
                            // LAYOUT: components/com_phocagallery/layouts/box_image.php
                            $v->class		= 'pg-photoswipe-button';
                            $v->class2		= 'pg-photoswipe-button-copy';
                            $v->class3		= 'pg-bs-modal-button';
                            $v->link 		= $imageL;
                            $v->link2 		= 'javascript:void(0)';
                            //$v->link3		= $siteLink;
                            //$v->linkorig		= $imgLinkOrig;
                                            $v->linkthumbnailpath = $imageM;
                            $v->onclick		= '';
                            $v->itemprop		= 'contentUrl';
                            $v->onclick2		= 'document.getElementById(\'pgImg'.$v->id.'\').click();';
                            $v->onclick3		= $v->onclick;
                            $v->oimgalt  = $v->title;
                            $d          = array();
                            $d['item']  = $v;
                            $d['t']     = [];
                            $o .= $layoutBI->render($d);


                            if ($photoswipe_display_caption > 0) {

                                $caption = $v->title;

                                if ($photoswipe_display_caption == 2) {
                                    $caption = $v->description;
                                }

                                if ($photoswipe_display_caption == 3) {

                                    $caption = '';
                                    if ($v->title != '') {
                                        $caption .= $v->title . '<br>';
                                    }
                                    $caption .= $v->description;
                                }

                                $o .= '<figcaption itemprop="caption description">'.$caption.'</figcaption>';
                            }


                            $o .= '</figure>';

                            if ($display_title == 1) {


                                $o .= '<div class="pg-item-box-title image">' . "\n";

                                $o .= '<svg class="ph-si ph-si-image"><use xlink:href="#ph-si-image"></use></svg>' . "\n";
                                $o .= ' <a class="' . $v->class2 . '" title="' . htmlentities($v->title, ENT_QUOTES, 'UTF-8') . '"'
                                    . ' data-img-title="' . $v->title . '" href="' . Route::_($v->link2) . '"';

                                if ($v->onclick2 != '') {
                                    $o .= 'onclick="' . $v->onclick2 . '"';
                                }
                                $o .= ' >';
                                $o .= '' . $v->title . '';
                                $o .= '</a>';

                                $o .= '</div>' . "\n";





                            }




                            //$o .= '</div>'; // end ph-gallery-plugin-image-box

                            //$o .= '</div>';// end ph-gallery-plugin-image-container
                            /*if ($display_title == 1) {
                                $o .= '<div class="ph-gallery-plugin-image-title">' . $v->title . '</div>';
                            }*/

                            $o .= '</div>';// end ph-gallery-plugin-box

                            /*if ($count == 1) {
                                $o .= '</div>'; // end column
                            }*/

                        }
                        //$o .= '</div>';
                       // $o .= '</div>';// end ph-gallery-plugin-container

                        $o .= '</div>';// end pswp

                        if ($i == ($count_matches - 1) && $detail_window == 1) {
                            // Must be at the end
                            $o .= $this->loadPhotoswipeBottomPlugin(1, 1);

                        }
                    }

                }

                //--------------------------
                // DISPLAYING OF CATEGORY - MASONRY
                //--------------------------


                if ($view == 'category-masonry') {

                    $document->addStyleSheet(Uri::root(true) . '/media/plg_content_phocagallery/css/phocagallery-masonry.css');

                    $limit = '';
                    $where = '';

                    // Max is the limit, if limitcount is smaller than max, use the limitcount
                    if ((int)$limitcount < (int)$max) {
                        $max = $limitcount;
                    }
                    if ((int)$limitcount > 0 && (int)$max == 0) {
                        $max = $limitcount;
                    }

                    if ($view == 'category-masonry') {
                        if ($max > 0) {
                            $limit = ' LIMIT '.(int)$limitstart.',' . (int)$max;
                        }
                        $where = ' AND a.catid = ' . (int)$id;
                    } else if ($view == 'image') {
                        $where = ' AND a.id =' . (int)$id;
                    } else {
                        if ($max > 0) {
                            $limit = ' LIMIT '.(int)$limitstart.',' . (int)$max;
                        }
                    }


                    $ordering = PhocaGalleryOrdering::getOrderingString($imageordering);


                    $query = 'SELECT a.id, a.catid, a.title, a.alias, a.filename, a.description, a.extm, a.exts, a.extw, a.exth, a.extid, a.extl, a.exto'
                        . ' FROM #__phocagallery AS a'
                        . ' WHERE a.published = 1'
                        . ' AND a.approved = 1'
                        . $where
                        .$ordering['output']
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

                        if ((int)$this->_plugin_number_category_view < 2) {
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

                            //$document->addStyleSheet(Uri::root(true) . '/media/com_phocagallery/js/photoswipe/css/photoswipe.css');
                           // $document->addStyleSheet(Uri::root(true) . '/media/com_phocagallery/js/photoswipe/css/default-skin/default-skin.css');
                            //$document->addStyleSheet(Uri::root(true) . '/media/com_phocagallery/js/photoswipe/css/photoswipe-style.css');
                            $wa = $document->getWebAssetManager();
		                    $wa->registerAndUseStyle('plg_content_phocagallery.photoswipe', 'media/com_phocagallery/js/photoswipe/css/photoswipe.css', array('version' => 'auto'));
                            $wa->registerAndUseStyle('plg_content_phocagallery.photoswipe.default', 'media/com_phocagallery/js/photoswipe/css/default-skin/default-skin.css', array('version' => 'auto'));
                            $wa->registerAndUseStyle('plg_content_phocagallery.photoswipe.style', 'media/com_phocagallery/js/photoswipe/css/photoswipe-style.css', array('version' => 'auto'));

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
// pg-category-items-box pg-photoswipe pg-msnr-container
                        $o .= '<div id="pg-msnr-container-a' . (int)$article->id . '-p' . (int)$this->_plugin_number_category_view . '" class="pg-photoswipe pg-msnr-container pg-masonry" itemscope itemtype="http://schema.org/ImageGallery">';


                        //$o .= '<div class="ph-gallery-plugin-container pg-category-view pg-cv">';
                        ///* id="pg-msnr-container-a'.(int)$article->id . '-p'. (int)$this->_plugin_number.'"

                        $class = '';
                        if ($display_title == 1) {
                            $class = ' ph-incl-title';
                        }

                        foreach ($images as $k => $v) {

                            $o .= '<div class="pg-masonry-item">'. "\n";// BOX START


                            $image = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'medium');


                            if ($v->extm != '') {
                                $imageM = $v->extm;
                                $imageL = $v->extl;
                            } else {
                                $imageMO = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'medium');
                                if (isset($imageMO->rel) && $imageMO->rel != '') {
                                    $imageM = JURI::base(false) . $imageMO->rel;
                                }
                                $imageLO = PhocaGalleryFileThumbnail::getThumbnailName($v->filename, 'large');
                                if (isset($imageLO->rel) && $imageLO->rel != '') {
                                    $imageL = JURI::base(false) . $imageLO->rel;
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
                                    if (isset($extWA[0])) {
                                        $w = $extWA[0];
                                    }
                                }

                                if (isset($v->exth) && $v->exth != '') {
                                    $extHA = explode(',', $v->exth);
                                    if (isset($extHA[0])) {
                                        $h = $extHA[0];
                                    }
                                }


                                /*if ($detail_window == 2) {

                                    if ($count == 1) {
                                        $o .= '<a href="'.$imageL.'" rel="prettyPhoto[\'pp_gal_plugin'.(int)$this->_plugin_number.'\']">';
                                    } else {
                                        $o .= '<a href="'.$imageL.'" rel="prettyPhoto">';
                                    }

                                } else {*/
                                ///$o .= '<a class="pg-photoswipe-button" href="' . $imageL . '" itemprop="contentUrl" data-size="' . $w . 'x' . $h . '" >';
                                /*}*/
                                $v->datasize = 'data-size="' . $w . 'x' . $h . '"';

                            }

                         /*   if ($imageM != '') {
                                $o .= '<img src="' . $imageM . '" alt="' . $v->title . '" class="c-Image c-Image--shaded" itemprop="thumbnail" />';
                            }

                            if ($imageL != '') {
                                $o .= '</a>';

                            }*/

                            // Display BOX IMAGE
                            // LAYOUT: components/com_phocagallery/layouts/box_image.php
                            $v->class		= 'pg-photoswipe-button';
                            $v->class2		= 'pg-photoswipe-button-copy';
                            $v->class3		= 'pg-bs-modal-button';
                            $v->link 		= $imageL;
                            $v->link2 		= 'javascript:void(0)';
                            //$v->link3		= $siteLink;
                            //$v->linkorig		= $imgLinkOrig;
                                            $v->linkthumbnailpath = $imageL;
                            $v->onclick		= '';
                            $v->itemprop		= 'contentUrl';
                            $v->onclick2		= 'document.getElementById(\'pgImg'.$v->id.'\').click();';
                            $v->onclick3		= $v->onclick;
                            $v->oimgalt  = $v->title;
                           /* $d          = array();
                            $d['item']  = $v;
                            $d['t']     = [];
                            $o .= $layoutBI->render($d);*/




                            $o .= '<a class="'.$v->class.'" href="'. $v->link.'" data-img-title="'.$v->title.'" id="pgImg'.$v->id.'"';

                            if (isset($v->onclick) && $v->onclick != '') {
                                $o .= ' onclick="'.$v->onclick.'"';
                            }

                            if (isset($v->itemprop) && $v->itemprop != '') {
                                $o .= ' itemprop="'.$v->itemprop.'"';
                            }

                            if (isset($v->datasize)) { $o .= ' '. $v->datasize;}

                            if (isset($v->videocode) && $v->videocode != '' && $v->videocode != '0') {
                                $o .= ' data-type="video" data-video="<div class=\'ph-pswp-wrapper\'><div class=\'ph-pswp-video-wrapper\'>' . str_replace('"', "'", PhocaGalleryYoutube::displayVideo($v->videocode)) . '</div></div>"';
                            }

                            $o .= ' >';

                           // $o .= '<div class="item">';
                            $o .= HTMLHelper::_( 'image', isset($v->extid) & (int)$v->extid> 0 ? $v->extm : $v->linkthumbnailpath, $v->oimgalt, array( 'class' => 'pg-image c-Image c-Image--shaded', 'itemprop' => "thumbnail"));


                           // $o .= '</div>';

                            $o .= '</a>';





                            if ($photoswipe_display_caption > 0) {

                                $caption = $v->title;

                                if ($photoswipe_display_caption == 2) {
                                    $caption = $v->description;
                                }

                                if ($photoswipe_display_caption == 3) {

                                    $caption = '';
                                    if ($v->title != '') {
                                        $caption .= $v->title . '<br>';
                                    }
                                    $caption .= $v->description;
                                }

                                $o .= '<figcaption itemprop="caption description">'.$caption.'</figcaption>';
                            }


                            $o .= '</figure>';



                            //$o .= '</div>'; // end ph-gallery-plugin-image-box

                            //$o .= '</div>';// end ph-gallery-plugin-image-container
                            /*if ($display_title == 1) {
                                $o .= '<div class="ph-gallery-plugin-image-title">' . $v->title . '</div>';
                            }*/

                            $o .= '</div>';// end ph-gallery-plugin-box

                            /*if ($count == 1) {
                                $o .= '</div>'; // end column
                            }*/

                        }
                        //$o .= '</div>';
                       // $o .= '</div>';// end ph-gallery-plugin-container

                        $o .= '</div>';// end pswp

                        if ($i == ($count_matches - 1) && $detail_window == 1) {
                            // Must be at the end

                            $o .= $this->loadPhotoswipeBottomPlugin(1, 1);
                        }
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


    public function loadPhotoswipeBottomPlugin($forceSlideshow = 0, $forceSlideEffect = 0) {

		//$paramsC 				= ComponentHelper::getParams('com_phocagallery') ;
		$photoswipe_slideshow	= 1;//$paramsC->get( 'photoswipe_slideshow', 1 );
		$photoswipe_slide_effect= 1;//$paramsC->get( 'photoswipe_slide_effect', 0 );


		if ($forceSlideshow == 1) {
            $photoswipe_slideshow = 1;
        }
		if ($forceSlideEffect == 1) {
		    $photoswipe_slide_effect = 1;
        }


		$o = '<!-- Root element of PhotoSwipe. Must have class pswp. -->
<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">

    <!-- Background of PhotoSwipe.
         It\'s a separate element, as animating opacity is faster than rgba(). -->
    <div class="pswp__bg"></div>

    <!-- Slides wrapper with overflow:hidden. -->
    <div class="pswp__scroll-wrap">

        <!-- Container that holds slides. PhotoSwipe keeps only 3 slides in DOM to save memory. -->
        <!-- don\'t modify these 3 pswp__item elements, data is added later on. -->
        <div class="pswp__container">
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
        </div>

        <!-- Default (PhotoSwipeUI_Default) interface on top of sliding area. Can be changed. -->
        <div class="pswp__ui pswp__ui--hidden">

            <div class="pswp__top-bar">

                <!--  Controls are self-explanatory. Order can be changed. -->

                <div class="pswp__counter"></div>

                <button class="pswp__button pswp__button--close" title="'.Text::_('COM_PHOCAGALLERY_CLOSE').'"></button>

                <button class="pswp__button pswp__button--share" title="'.Text::_('COM_PHOCAGALLERY_SHARE').'"></button>

                <button class="pswp__button pswp__button--fs" title="'.Text::_('COM_PHOCAGALERY_TOGGLE_FULLSCREEN').'"></button>

                <button class="pswp__button pswp__button--zoom" title="'.Text::_('COM_PHOCAGALLERY_ZOOM_IN_OUT').'"></button>';

				if ($photoswipe_slideshow == 1) {
					$o .= '<!-- custom slideshow button: -->
					<button class="pswp__button pswp__button--playpause" title="'.Text::_('COM_PHOCAGALLERY_PLAY_SLIDESHOW').'"></button>
					<span id="phTxtPlaySlideshow" style="display:none">'.Text::_('COM_PHOCAGALLERY_PLAY_SLIDESHOW').'</span>
					<span id="phTxtPauseSlideshow" style="display:none">'.Text::_('COM_PHOCAGALLERY_PAUSE_SLIDESHOW').'</span>';
				}

                $o .= '<!-- Preloader -->
                <!-- element will get class pswp__preloader--active when preloader is running -->
                <div class="pswp__preloader">
                    <div class="pswp__preloader__icn">
                      <div class="pswp__preloader__cut">
                        <div class="pswp__preloader__donut"></div>
                      </div>
                    </div>
                </div>
            </div>

            <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
                <div class="pswp__share-tooltip"></div> 
            </div>

            <button class="pswp__button pswp__button--arrow--left" title="'.Text::_('COM_PHOCAGALLERY_PREVIOUS').'">
            </button>

            <button class="pswp__button pswp__button--arrow--right" title="'.Text::_('COM_PHOCAGALLERY_NEXT').'">
            </button>

            <div class="pswp__caption">
                <div class="pswp__caption__center"></div>
            </div>

          </div>

        </div>

</div>';


                $wa = Factory::getDocument()->getWebAssetManager();
                $wa->registerAndUseScript('plg_content_phocagallery.photoswipe', 'media/com_phocagallery/js/photoswipe/js/photoswipe.min.js', array('version' => 'auto'), ['defer' => true]);
                $wa->registerAndUseScript('plg_content_phocagallery.photoswipe.default', 'media/com_phocagallery/js/photoswipe/js/photoswipe-ui-default.min.js', array('version' => 'auto'), ['defer' => true]);


//$o .=   '<script src="'.Uri::root(true).'/media/com_phocagallery/js/photoswipe/js/photoswipe.min.js"></script>'. "\n"
//		.'<script src="'.Uri::root(true).'/media/com_phocagallery/js/photoswipe/js/photoswipe-ui-default.min.js"></script>'. "\n";

if ($photoswipe_slide_effect == 1) {
	//$o .= '<script src="'.Uri::root(true).'/media/com_phocagallery/js/photoswipe/js/photoswipe-initialize-ratio.js"></script>'. "\n";
    $wa->registerAndUseScript('plg_content_phocagallery.photoswipe.initialize.ratio', 'media/com_phocagallery/js/photoswipe/js/photoswipe-initialize-ratio.js', array('version' => 'auto'), ['defer' => true]);
} else {
	//$o .= '<script src="'.Uri::root(true).'/media/com_phocagallery/js/photoswipe/js/photoswipe-initialize.js"></script>'. "\n";
    $wa->registerAndUseScript('plg_content_phocagallery.photoswipe.initialize.ratio', 'media/com_phocagallery/js/photoswipe/js/photoswipe-initialize.js', array('version' => 'auto'), ['defer' => true]);
}

		return $o;
	}
}

?>
