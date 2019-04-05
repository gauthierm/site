<?php

/**
 * An image data object
 *
 * @package   Site
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteImage extends SwatDBDataObject
{
    // {{{ public properties

    /**
     * The base uri for CDN hosted images
     *
     * @var string
     */
    public static $cdn_base;

    /**
     * Unique identifier
     *
     * @var integer
     */
    public $id;

    /**
     * Filename
     *
     * Only used if the ImageSet for this image sets obfuscate_filename = true.
     *
     * @var string
     */
    public $filename;

    /**
     * Original filename
     *
     * The original name of the file before it was processed.
     *
     * @var string
     */
    public $original_filename;

    /**
     * Title
     *
     * @var string
     */
    public $title;

    /**
     * Description
     *
     * @var string
     */
    public $description;

    // }}}
    // {{{ protected properties

    protected $image_set_shortname;
    protected $automatically_save = true;
    protected $imagick_instances = array();

    // }}}
    // {{{ private properties

    private static $image_set_cache = array();
    private $file_base;
    private $crop_box;
    private $crop_boxes = array();
    private $original_dpi;

    // }}}

    // dataobject methods
    // {{{ public function load()

    /**
     * Loads this object's properties from the database given an id
     *
     * @param mixed $id the id of the database row to set this object's
     *               properties with.
     *
     * @return boolean whether data was sucessfully loaded.
     */
    public function load($id)
    {
        $loaded = parent::load($id);

        if ($loaded && $this->image_set_shortname !== null) {
            if ($this->image_set->shortname != $this->image_set_shortname) {
                throw new SwatException(
                    'Trying to load image with the ' .
                        'wrong image set. This may happen if the wrong wrapper ' .
                        'class is used.'
                );
            }
        }

        if ($loaded) {
            // pre-load dimension bindings. This is useful if the object is
            // serialized before dimensions are loaded.
            $this->setSubDataObject(
                'dimension_bindings',
                $this->loadDimensionBindings()
            );
        }

        return $loaded;
    }

    // }}}
    // {{{ public function getTitle()

    /**
     * Gets the title of this image
     *
     * @return string the title of this image.
     */
    public function getTitle()
    {
        return $this->title;
    }

    // }}}
    // {{{ public function setOnCdn()

    /**
     * Sets the on_cdn column on the image dimension binding
     *
     * @param boolean $on_cdn the new value for on_cdn.
     * @param string $dimension_shortname the shortname of the image dimension
     *                                     to update.
     */
    public function setOnCdn($on_cdn, $dimension_shortname)
    {
        $dimension = $this->image_set->getDimensionByShortname(
            $dimension_shortname
        );

        $sql = sprintf(
            'update ImageDimensionBinding set on_cdn = %s where
			image = %s and dimension = %s',
            $this->db->quote($on_cdn, 'boolean'),
            $this->db->quote($this->id, 'integer'),
            $this->db->quote($dimension->id, 'integer')
        );

        SwatDB::exec($this->db, $sql);
    }

    // }}}
    // {{{ public function hasImageSet()

    /**
     * Whether or not the image classes image set exists.
     *
     * @return boolean true if does exist, false if it doesn't.
     */
    public function hasImageSet()
    {
        $has_image_set = true;
        try {
            $this->getImageSet();
        } catch (Exception $e) {
            $has_image_set = false;
        }

        return $has_image_set;
    }

    // }}}
    // {{{ public function getImageSetShortname()

    /**
     * @return string the image set shortname
     */
    public function getImageSetShortname()
    {
        return $this->image_set_shortname;
    }

    // }}}
    // {{{ public function getValidMimeTypes()

    public function getValidMimeTypes()
    {
        return array('image/jpeg', 'image/png', 'image/tiff', 'image/gif');
    }

    // }}}
    // {{{ public function getHumanFileType()

    public function getHumanFileType($mime_type = null)
    {
        if ($mime_type == '') {
            $mime_type = $this->mime_type;
        }

        $map = array(
            'image/jpeg' => Site::_('JPEG Image'),
            'image/png' => Site::_('PNG Image'),
            'image/tiff' => Site::_('TIFF Image'),
            'image/gif' => Site::_('GIF Image')
        );

        if (!array_key_exists($mime_type, $map)) {
            throw new SiteException(
                sprintf('Unknown mime type %s', $mime_type)
            );
        }

        return $map[$mime_type];
    }

    // }}}
    // {{{ public function getHumanFileTypes()

    public function getHumanFileTypes(array $mime_types)
    {
        $human_file_types = array();

        foreach ($mime_types as $mime_type) {
            $human_file_types[$mime_type] = $this->getHumanFileType($mime_type);
        }

        return $human_file_types;
    }

    // }}}
    // {{{ public function getValidHumanFileTypes()

    public function getValidHumanFileTypes()
    {
        return $this->getHumanFileTypes($this->getValidMimeTypes());
    }

    // }}}
    // {{{ protected function init()

    protected function init()
    {
        $this->registerInternalProperty(
            'image_set',
            SwatDBClassMap::get('SiteImageSet')
        );

        $this->table = 'Image';
        $this->id_field = 'integer:id';
    }

    // }}}
    // {{{ protected function hasSubDataObject()

    protected function hasSubDataObject($key)
    {
        $found = parent::hasSubDataObject($key);

        if ($key === 'image_set' && !$found) {
            $image_set_id = $this->getInternalValue('image_set');

            if (
                $image_set_id !== null &&
                array_key_exists($image_set_id, self::$image_set_cache)
            ) {
                $this->setSubDataObject(
                    'image_set',
                    self::$image_set_cache[$image_set_id]
                );

                $found = true;
            }
        }

        return $found;
    }

    // }}}
    // {{{ protected function setSubDataObject()

    protected function setSubDataObject($name, $value)
    {
        if ($name === 'image_set') {
            self::$image_set_cache[$value->id] = $value;
        }

        parent::setSubDataObject($name, $value);
    }

    // }}}
    // {{{ protected function deleteInternal()

    /**
     * Deletes this object from the database and any images files
     * corresponding to this object
     */
    protected function deleteInternal()
    {
        $this->deleteCdnFiles();

        $local_files = $this->getLocalFilenamesToDelete();

        parent::deleteInternal();

        $this->deleteLocalFiles($local_files);
    }

    // }}}
    // {{{ protected function getLocalFilenamesToDelete()

    /**
     * Gets an array of files names to delete when deleting this object
     *
     * @return array an array of filenames.
     */
    protected function getLocalFilenamesToDelete()
    {
        $filenames = array();

        foreach ($this->getImageSet()->dimensions as $dimension) {
            $filenames[] = $this->getFilePath($dimension->shortname);
        }

        return $filenames;
    }

    // }}}
    // {{{ protected function deleteCdnFiles()

    protected function deleteCdnFiles()
    {
        foreach ($this->getImageSet()->dimensions as $dimension) {
            $binding = $this->getDimensionBinding($dimension->shortname);

            if (
                $binding instanceof SiteImageDimensionBinding &&
                $binding->on_cdn
            ) {
                $this->queueCdnTask('delete', $dimension);
            }
        }
    }

    // }}}
    // {{{ protected function deleteLocalFiles()

    /**
     * Deletes each file in a given set of filenames
     *
     * @param array $filenames an array of filenames to delete.
     */
    protected function deleteLocalFiles(array $filenames)
    {
        foreach ($filenames as $filename) {
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
    }

    // }}}
    // {{{ protected function getImageDimensionBindingClassName()

    protected function getImageDimensionBindingClassName()
    {
        return SwatDBClassMap::get('SiteImageDimensionBinding');
    }

    // }}}
    // {{{ protected function getImageDimensionBindingWrapperClassName()

    protected function getImageDimensionBindingWrapperClassName()
    {
        return SwatDBClassMap::get('SiteImageDimensionBindingWrapper');
    }

    // }}}
    // {{{ protected function getSerializableSubDataObjects()

    protected function getSerializableSubDataObjects()
    {
        return array('image_set', 'dimension_bindings');
    }

    // }}}
    // {{{ protected function getSerializablePrivateProperties()

    protected function getSerializablePrivateProperties()
    {
        return array_merge(parent::getSerializablePrivateProperties(), array(
            'image_set_shortname'
        ));
    }

    // }}}

    // image methods
    // {{{ public function hasDimension()

    public function hasDimension($dimension_shortname)
    {
        $found = false;

        if ($this->image_set->hasDimension($dimension_shortname)) {
            $binding = $this->getDimensionBinding($dimension_shortname);
            $found = $binding !== null;
        }

        return $found;
    }

    // }}}
    // {{{ public function getWidth()

    public function getWidth($dimension_shortname)
    {
        $binding = $this->getDimensionBinding($dimension_shortname);

        if (!$binding instanceof SiteImageDimensionBinding) {
            throw new SiteInvalidImageDimensionException(
                sprintf(
                    'Image dimension “%s” does not exist for image %s.',
                    $dimension_shortname,
                    $this->id
                )
            );
        }

        return $binding->width;
    }

    // }}}
    // {{{ public function getHeight()

    public function getHeight($dimension_shortname)
    {
        $binding = $this->getDimensionBinding($dimension_shortname);

        if (!$binding instanceof SiteImageDimensionBinding) {
            throw new SiteInvalidImageDimensionException(
                sprintf(
                    'Image dimension “%s” does not exist for image %s.',
                    $dimension_shortname,
                    $this->id
                )
            );
        }

        return $binding->height;
    }

    // }}}
    // {{{ public function getFilesize()

    public function getFilesize($dimension_shortname)
    {
        $binding = $this->getDimensionBinding($dimension_shortname);
        return $binding->filesize;
    }

    // }}}
    // {{{ public function getFilename()

    public function getFilename($shortname)
    {
        $extension = $this->getExtension($shortname);

        if ($this->image_set->obfuscate_filename) {
            $filename = $this->filename;
        } else {
            $filename = $this->id;
        }

        return sprintf('%s.%s', $filename, $extension);
    }

    // }}}
    // {{{ public function getExtension()

    public function getExtension($shortname)
    {
        // get extension if it exists, otherwise get the default from dimension
        $binding = $this->getDimensionBinding($shortname);
        if ($binding === null) {
            $dimension = $this->image_set->getDimensionByShortname($shortname);
            $extension = $dimension->default_type->extension;
        } else {
            $extension = $binding->image_type->extension;
        }

        return $extension;
    }

    // }}}
    // {{{ public function getDpi()

    public function getDpi($dimension_shortname)
    {
        $binding = $this->getDimensionBinding($dimension_shortname);
        return $binding->dpi;
    }

    // }}}
    // {{{ public function getMimeType()

    public function getMimeType($dimension_shortname)
    {
        $binding = $this->getDimensionBinding($dimension_shortname);
        return $binding->image_type->mime_type;
    }

    // }}}
    // {{{ public function getUri()

    public function getUri($shortname, $prefix = null)
    {
        $uri = $this->getUriSuffix($shortname);

        // Don't apply the prefix if the image exists on a CDN since the image
        // will always be in the same location. We don't need to apply ../ for
        // images displayed in the admin.
        $binding = $this->getDimensionBinding($shortname);
        if ($binding->on_cdn && self::$cdn_base != null) {
            $uri = self::$cdn_base . $uri;
        } elseif ($prefix !== null && !mb_strpos($uri, '://')) {
            $uri = $prefix . $uri;
        }

        return $uri;
    }

    // }}}
    // {{{ public function getUriSuffix()

    public function getUriSuffix($shortname)
    {
        $dimension = $this->image_set->getDimensionByShortname($shortname);

        $suffix = sprintf(
            '%s/%s/%s',
            $this->image_set->shortname,
            $dimension->shortname,
            $this->getFilename($shortname)
        );

        if ($this->getUriBase() !== null) {
            $suffix = $this->getUriBase() . '/' . $suffix;
        }

        return $suffix;
    }

    // }}}
    // {{{ public function getFilePath()

    /**
     * Gets the full file path of a dimension
     *
     * This includes the directory and the filename.
     *
     * @return string the full file path of a dimension.
     */
    public function getFilePath($shortname)
    {
        $directory = $this->getFileDirectory($shortname);
        $filename = $this->getFilename($shortname);
        return $directory . DIRECTORY_SEPARATOR . $filename;
    }

    // }}}
    // {{{ public function getFileDirectory()

    /**
     * Gets the directory of a dimension
     *
     * @return string the directory of a dimension.
     */
    public function getFileDirectory($shortname)
    {
        $dimension = $this->image_set->getDimensionByShortname($shortname);

        return $this->getFileBase() .
            DIRECTORY_SEPARATOR .
            $this->image_set->shortname .
            DIRECTORY_SEPARATOR .
            $dimension->shortname;
    }

    // }}}
    // {{{ public function getImgTag()

    public function getImgTag($shortname, $prefix = null)
    {
        // don't use getImageSet() here as this should always be loaded with
        // the database value for image_set, and instance does not have to be
        // specified to display an image.
        $dimension = $this->image_set->getDimensionByShortname($shortname);

        $img_tag = new SwatHtmlTag('img');
        $img_tag->src = $this->getUri($shortname, $prefix);

        $img_tag->width = $this->getWidth($shortname);
        $img_tag->height = $this->getHeight($shortname);

        $title = $this->getTitle();
        if ($title !== null) {
            $img_tag->alt = sprintf(Site::_('Image of %s'), $title);
            $img_tag->title = $title;
        } else {
            $img_tag->alt = '';
        }

        return $img_tag;
    }

    // }}}
    // {{{ public function getHalfSizeImgTag()

    /**
     * Returns an image with its height and width set to half its actual
     * dimensions.
     *
     * This is for easy use of the retina technique of allowing the browser to
     * down-size a larger lower quality compressed image from its actual size
     * to half its size. See
     * @link{http://filamentgroup.com/lab/rwd_img_compression/} and
     * @link{http://www.netvlies.nl/blog/design-interactie/retina-revolution}
     * for a full explanation of this technique. This assumes sane compression
     * and resize filter settings have been set on the image dimension being
     * displayed.
     *
     * @param string $shortname the shortname of the image dimension to use.
     * @param string $prefix optional url prefix for the image href
     *
     * @return SwatHtmlTag the image tag for use.
     */
    public function getHalfSizeImgTag($shortname, $prefix = null)
    {
        $img_tag = $this->getImgTag($shortname, $prefix);

        // Round down in case the width and height are not perfectly divisible
        // by two.
        $img_tag->width = floor($img_tag->width / 2);
        $img_tag->height = floor($img_tag->height / 2);

        return $img_tag;
    }

    // }}}
    // {{{ public function setFileBase()

    public function setFileBase($path)
    {
        $this->file_base = $path;
    }

    // }}}
    // {{{ public function getHttpHeaders()

    public function getHttpHeaders($dimension_shortname)
    {
        $headers = array();

        // Set a "never-expire" policy with a far future max age (10 years) as
        // suggested http://developer.yahoo.com/performance/rules.html#expires.
        // As well, set Cache-Control to public, as this allows some browsers to
        // cache the images to disk while on https, which is a good win. This
        // depends on setting new object ids when updating the object, if this
        // isn't true of a subclass this will have to be overwritten.
        $headers['Cache-Control'] = 'public, max-age=315360000';

        $headers['Content-Type'] = $this->getMimeType($dimension_shortname);

        return $headers;
    }

    // }}}
    // {{{ public function getLargestDimension()

    public function getLargestDimension()
    {
        $largest_width = 0;
        $largest_dimension = null;

        // Base largest only on width instead of area as most dimensions are
        // constrained by width. Subclass where not true.
        foreach ($this->getImageSet()->dimensions as $dimension) {
            try {
                $width = $this->getWidth($dimension->shortname);
                if ($width > $largest_width) {
                    $largest_width = $width;
                    $largest_dimension = $dimension;
                }
            } catch (SiteInvalidImageDimensionException $e) {
            }
        }

        return $largest_dimension;
    }

    // }}}
    // {{{ protected function getUriBase()

    protected function getUriBase()
    {
        return 'images';
    }

    // }}}
    // {{{ protected function getFileBase()

    protected function getFileBase()
    {
        if ($this->file_base === null) {
            throw new SwatException(
                'File base has not been set on the ' .
                    'dataobject. Set the path to the webroot using ' .
                    'setFileBase().'
            );
        }

        return $this->file_base;
    }

    // }}}
    // {{{ protected function getDimensionBinding()

    protected function getDimensionBinding($dimension_shortname)
    {
        $dimension = $this->image_set->getDimensionByShortname(
            $dimension_shortname
        );

        foreach ($this->dimension_bindings as $binding) {
            $id =
                $binding->dimension instanceof SiteImageDimension
                    ? $binding->dimension->id
                    : $binding->dimension;

            if ($dimension->id === $id) {
                return $binding;
            }
        }

        return null;
    }

    // }}}

    // loader methods
    // {{{ protected function loadDimensionBindings()

    /**
     * Loads the dimension bindings for this image
     *
     * @return SiteImageDimensionBindingWrapper a recordset of dimension
     *                                           bindings.
     */
    protected function loadDimensionBindings()
    {
        $sql = 'select * from ImageDimensionBinding
				where ImageDimensionBinding.image = %s';

        $sql = sprintf($sql, $this->db->quote($this->id, 'integer'));

        $wrapper = $this->getImageDimensionBindingWrapperClassName();
        return SwatDB::query($this->db, $sql, $wrapper);
    }

    // }}}

    // processing methods
    // {{{ public function process()

    /**
     * Does resizing for images
     *
     * The image is resized for each dimension. The dataobject is automatically
     * saved after the image has been processed.
     *
     * @param string $image_file the image file to process
     *
     * @throws SwatException if the image can't be processed.
     */
    public function process($image_file)
    {
        if ($this->automatically_save) {
            $this->checkDB();
        }

        $this->prepareForProcessing($image_file);

        try {
            // save once to set id on this object to use for filenames
            if ($this->automatically_save) {
                $transaction = new SwatDBTransaction($this->db);
                $this->save();
            }

            foreach ($this->image_set->dimensions as $dimension) {
                $this->processDimension($image_file, $dimension);
            }

            // Reset the instances to an empty array.
            $this->imagick_instances = array();

            // save again to record dimensions
            if ($this->automatically_save) {
                $this->save();
                $transaction->commit();
            }
        } catch (Exception $e) {
            if ($this->automatically_save) {
                $transaction->rollback();
            }

            throw $e;
        }
    }

    // }}}
    // {{{ public function processManual()

    /**
     * Manually process one dimension of an image
     *
     * @param string $image_file the image file to process
     * @param string $shortname the shortname of the dimension to process
     */
    public function processManual($image_file, $shortname)
    {
        if ($this->automatically_save) {
            $this->checkDB();
        }

        $this->prepareForProcessing($image_file);

        try {
            if ($this->automatically_save) {
                $transaction = new SwatDBTransaction($this->db);

                // save once to set id on this object to use for filenames
                $this->save();
            }

            $dimension = $this->image_set->getDimensionByShortname($shortname);
            $this->processDimension($image_file, $dimension);

            // Reset the instances to an empty array.
            $this->imagick_instances = array();

            if ($this->automatically_save) {
                // save again to record dimensions
                $this->save();
                $transaction->commit();
            }
        } catch (Exception $e) {
            if ($this->automatically_save) {
                $transaction->rollback();
            }

            throw $e;
        }
    }

    // }}}
    // {{{ public function processMissingDimensions()

    public function processMissingDimensions($image_file)
    {
        foreach ($this->getImageSet()->dimensions as $dimension) {
            if (!$this->hasDimension($dimension->shortname)) {
                $this->processManual($image_file, $dimension->shortname);
            }
        }
    }

    // }}}
    // {{{ public function processMissingDimensionsFromDimension()

    public function processMissingDimensionsFromDimension($shortname)
    {
        $dimension = $this->getDimension($shortname);
        if ($dimension instanceof SiteImageDimension) {
            $this->processMissingDimensions(
                $this->getFilePath($dimension->shortname)
            );
        } else {
            throw new SiteException(
                sprintf('Dimension ‘%s’ does not exist.', $shortname)
            );
        }
    }

    // }}}
    // {{{ public function processMissingDimensionsFromLargestDimension()

    public function processMissingDimensionsFromLargestDimension()
    {
        $largest = $this->getLargestDimension();
        if ($largest instanceof SiteImageDimension) {
            $this->processMissingDimensions(
                $this->getFilePath($largest->shortname)
            );
        } else {
            throw new SiteInvalidImageDimensionException(
                'Largest dimension does not exist.'
            );
        }
    }

    // }}}
    // {{{ public function setDpi()

    /**
     * Specify the DPI of the image being processed
     *
     * @param integer $dpi The dpi of the image being processed
     */
    public function setDpi($dpi)
    {
        $this->original_dpi = $dpi;
    }

    // }}}
    // {{{ public function setCropBox()

    /**
     * Specify a crop bounding-box
     *
     * The dimensions and positions for the crop box should be at the scale of
     * the source image. If a crop box exists, the image will first be cropped
     * to the specified coordinates and then default resizing will be applied.
     * Optionally, crop boxes can be specified per dimension.  In this case
     * the crop box will only be used for the dimension specified.
     *
     * @param integer $width Width of the crop bounding box
     * @param integer $height Height of the crop bounding box
     * @param integer $offset_x Position of the top side of the crop bounding
     *                box
     * @param integer $offset_y Position of the left side of the crop bounding
     *                box
     * @param string $shortname The shortname of the dimension
     */
    public function setCropBox(
        $width,
        $height,
        $offset_x,
        $offset_y,
        $dimension_shortname = null
    ) {
        $crop_box = array($width, $height, $offset_x, $offset_y);

        if ($dimension_shortname === null) {
            $this->crop_box = $crop_box;
        } else {
            $this->crop_boxes[$dimension_shortname] = $crop_box;
        }
    }

    // }}}
    // {{{ protected function processDimension()

    /**
     * Resizes an image for the given dimension
     *
     * @param string $image_file location of image to process.
     * @param SiteImageDimension $dimension the dimension to process.
     */
    protected function processDimension(
        $image_file,
        SiteImageDimension $dimension
    ) {
        $imagick = $this->getImagick($image_file, $dimension);

        $this->processDimensionInternal($imagick, $dimension);
        $this->saveDimensionBinding($imagick, $dimension);

        if (
            $dimension->max_width === null &&
            $dimension->max_height === null &&
            $dimension->default_type === null
        ) {
            $this->copyFile($image_file, $dimension);
        } else {
            $this->saveFile($imagick, $dimension);
        }

        // This needs to happen after the files have been saved to disk. The
        // inital save of each dimension happens in saveDimensionBinding()
        // before the files are saved so that if DB errors happen orphan files
        // do not end up on disk. Saving the binding twice seems more effecient
        // than using a temporay file on disk to get filesize.
        $this->saveDimensionBindingFileSize($dimension);

        if ($this->getImageSet()->use_cdn) {
            $this->queueCdnTask('copy', $dimension);
        }

        unset($imagick);
    }

    // }}}
    // {{{ protected function processDimensionInternal()

    protected function processDimensionInternal(
        Imagick $imagick,
        SiteImageDimension $dimension
    ) {
        $crop_box = $this->getCropBox($dimension);

        if ($crop_box !== null) {
            $this->cropToBox($imagick, $crop_box);
        }

        if ($dimension->crop) {
            $this->cropToDimension($imagick, $dimension);
        } else {
            $this->fitToDimension($imagick, $dimension);
        }
    }

    // }}}
    // {{{ protected function cropToDimension()

    /**
     * Resizes and crops an image to a given dimension
     *
     * @param Imagick $imagick the imagick instance to work with.
     * @param SiteImageDimension $dimension the dimension to process.
     */
    protected function cropToDimension(
        Imagick $imagick,
        SiteImageDimension $dimension
    ) {
        $height = $dimension->max_height;
        $width = $dimension->max_width;

        if (
            $imagick->getImageWidth() === $dimension->max_width &&
            $imagick->getImageHeight() === $dimension->max_height
        ) {
            return;
        }

        if (
            $imagick->getImageWidth() / $width >
            $imagick->getImageHeight() / $height
        ) {
            $new_height = $height;
            $new_width = ceil(
                $imagick->getImageWidth() *
                    ($new_height / $imagick->getImageHeight())
            );
        } else {
            $new_width = $width;
            $new_height = ceil(
                $imagick->getImageHeight() *
                    ($new_width / $imagick->getImageWidth())
            );
        }

        $this->setDimensionDpi($imagick, $dimension, $new_width);

        $imagick->resizeImage(
            $new_width,
            $new_height,
            $this->getResizeFilter($dimension),
            1
        );

        // Set page geometry to the new size so subsequent crops will use
        // will use the geometry of the new image instead of the original
        // image.
        $imagick->setImagePage($new_width, $new_height, 0, 0);

        // crop to fit
        if (
            $imagick->getImageWidth() != $width ||
            $imagick->getImageHeight() != $height
        ) {
            list($offset_x, $offset_y) = $this->calculateCropToDimensionOffset(
                $imagick,
                $dimension
            );

            $imagick->cropImage($width, $height, $offset_x, $offset_y);

            // Set page geometry to the newly cropped size so subsequent crops
            // will use the geometry of the new image instead of the original
            // image.
            $imagick->setImagePage($width, $height, $offset_x, $offset_y);
        }
    }

    // }}}
    // {{{ protected function calculateCropToDimensionOffset()

    /**
     * Calculate the offsets when cropping to a dimension
     *
     * @param Imagick $imagick the imagick instance to work with.
     * @param SiteImageDimension $dimension the dimension to process.
     *
     * @return array The x and y offsets
     */
    protected function calculateCropToDimensionOffset(
        Imagick $imagick,
        SiteImageDimension $dimension
    ) {
        $height = $dimension->max_height;
        $width = $dimension->max_width;

        $offset_x = 0;
        $offset_y = 0;

        if ($imagick->getImageWidth() > $width) {
            $offset_x = ceil(($imagick->getImageWidth() - $width) / 2);
        }

        if ($imagick->getImageHeight() > $height) {
            $offset_y = ceil(($imagick->getImageHeight() - $height) / 2);
        }

        return array($offset_x, $offset_y);
    }

    // }}}
    // {{{ protected function cropToBox()

    /**
     * Resizes and crops an image to a given crop bounding box
     *
     * @param Imagick $imagick the imagick instance to work with.
     * @param array $bounding_box the dimension to process.
     */
    protected function cropToBox(Imagick $imagick, array $bounding_box)
    {
        list($width, $height, $offset_x, $offset_y) = $bounding_box;

        $imagick->cropImage($width, $height, $offset_x, $offset_y);

        // Set page geometry to the newly cropped size so subsequent crops
        // will use the geometry of the new image instead of the original
        // image.
        $imagick->setImagePage($width, $height, $offset_x, $offset_y);
    }

    // }}}
    // {{{ protected function fitToDimension()

    /**
     * Resizes an image to fit in a given dimension
     *
     * @param Imagick $imagick the imagick instance to work with.
     * @param SiteImageDimension $dimension the dimension to process.
     */
    protected function fitToDimension(
        Imagick $imagick,
        SiteImageDimension $dimension
    ) {
        $this->setDimensionDpi($imagick, $dimension);

        if (
            $dimension->max_width !== null &&
            $imagick->getImageWidth() > $dimension->max_width
        ) {
            $new_width = min($dimension->max_width, $imagick->getImageWidth());

            $new_height = ceil(
                $imagick->getImageHeight() *
                    ($new_width / $imagick->getImageWidth())
            );

            $this->setDimensionDpi($imagick, $dimension, $new_width);

            $imagick->resizeImage(
                $new_width,
                $new_height,
                $this->getResizeFilter($dimension),
                1
            );

            // Set page geometry to the new size so subsequent crops will use
            // will use the geometry of the new image instead of the original
            // image.
            $imagick->setImagePage($new_width, $new_height, 0, 0);
        }

        if (
            $dimension->max_height !== null &&
            $imagick->getImageHeight() > $dimension->max_height
        ) {
            $new_height = min(
                $dimension->max_height,
                $imagick->getImageHeight()
            );

            $new_width = ceil(
                $imagick->getImageWidth() *
                    ($new_height / $imagick->getImageHeight())
            );

            $this->setDimensionDpi($imagick, $dimension, $new_width);

            $imagick->resizeImage(
                $new_width,
                $new_height,
                $this->getResizeFilter($dimension),
                1
            );

            // Set page geometry to the new size so subsequent crops will use
            // will use the geometry of the new image instead of the original
            // image.
            $imagick->setImagePage($new_width, $new_height, 0, 0);
        }

        if (
            $dimension->upscale &&
            ($dimension->max_height !== null ||
                $dimension->max_width !== null) &&
            ($dimension->max_height === null ||
                $imagick->getImageHeight() < $dimension->max_height) &&
            ($dimension->max_width === null ||
                $imagick->getImageWidth() < $dimension->max_width)
        ) {
            if ($dimension->max_width !== null) {
                $new_width = $dimension->max_width;
                $new_height = ceil(
                    $imagick->getImageHeight() *
                        ($new_width / $imagick->getImageWidth())
                );
            }

            if (
                $dimension->max_height !== null &&
                ($dimension->max_width === null ||
                    $new_height > $dimension->max_height)
            ) {
                $new_height = $dimension->max_height;
                $new_width = ceil(
                    $imagick->getImageWidth() *
                        ($new_height / $imagick->getImageHeight())
                );
            }

            $this->setDimensionDpi($imagick, $dimension, $new_width);

            $imagick->resizeImage(
                $new_width,
                $new_height,
                $this->getResizeFilter($dimension),
                1
            );

            // Set page geometry to the new size so subsequent crops will use
            // will use the geometry of the new image instead of the original
            // image.
            $imagick->setImagePage($new_width, $new_height, 0, 0);
        }

        if ($this->getCropBox($dimension) === null) {
            $this->imagick_instances[$dimension->shortname] =
                floatval(phpversion('imagick')) >= 3.1
                    ? clone $imagick
                    : $imagick->clone();
        }
    }

    // }}}
    // {{{ protected function setDimensionDpi()

    protected function setDimensionDpi(
        Imagick $imagick,
        SiteImageDimension $dimension,
        $resized_width = null
    ) {
        if ($resized_width === null) {
            $resized_width = 1;
            $original_width = 1;
        } elseif ($this->getOriginalImagick() !== false) {
            $original_width = $this->getOriginalImagick()->getImageWidth();
        } else {
            $original_width = $imagick->getImageWidth();
        }

        $dpi =
            $this->original_dpi === null
                ? $dimension->dpi
                : round(
                    $this->original_dpi / ($original_width / $resized_width)
                );

        $imagick->setImageResolution($dpi, $dpi);
    }

    // }}}
    // {{{ protected function saveDimensionBinding()

    /**
     * Saves an image dimension binding
     *
     * @param Imagick $imagick the imagick instance to work with.
     * @param SiteImageDimension $dimension the image's dimension.
     */
    protected function saveDimensionBinding(
        Imagick $imagick,
        SiteImageDimension $dimension
    ) {
        $class_name = $this->getImageDimensionBindingClassName();
        $binding = new $class_name();
        $binding->image = $this->id;
        $binding->dimension = $dimension->id;
        $binding->width = $imagick->getImageWidth();
        $binding->height = $imagick->getImageHeight();
        $binding->image_type = $this->getDimensionImageType(
            $imagick,
            $dimension
        );

        $resolution = $imagick->getImageResolution();
        $binding->dpi = intval($resolution['x']);

        if ($this->automatically_save) {
            $binding->setDatabase($this->db);
            $binding->save();
        }

        $this->dimension_bindings->add($binding);
    }

    // }}}
    // {{{ protected function saveDimensionBindingFileSize()

    /**
     * Saves a dimension binding's filesize.
     *
     * @param SiteImageDimension $dimension the image's dimension.
     */
    protected function saveDimensionBindingFileSize(
        SiteImageDimension $dimension
    ) {
        $binding = $this->getDimensionBinding($dimension->shortname);
        if ($binding instanceof SiteImageDimensionBinding) {
            // Binding has to duplicated or SwatDBDataObject will try to insert
            // a row using SwatDBDataObject::saveNewBinding() and fail.
            $binding = $binding->duplicate();
            $binding->filesize = $this->getDimensionBindingFileSize(
                $dimension,
                $binding
            );

            if ($this->automatically_save) {
                $binding->setDatabase($this->db);
                $binding->save();
            }
        }
    }

    // }}}
    // {{{ protected function getDimensionImageType()

    /**
     * Gets the image type for a dimension. If default image type is specified,
     * the image is converted to that type, otherwise the type of the image is
     * preserved.
     *
     * @param Imagick $imagick the imagick instance to work with.
     * @param SiteImageDimension $dimension the image's dimension.
     *
     * @return SiteImageType The type of image for the dimension
     */
    protected function getDimensionImageType(
        Imagick $imagick,
        SiteImageDimension $dimension
    ) {
        if ($dimension->default_type === null) {
            $class_name = SwatDBClassMap::get('SiteImageType');
            $image_type = new $class_name();
            $image_type->setDatabase($this->db);
            $mime_type = 'image/' . $imagick->getImageFormat();
            $found = $image_type->loadByMimeType($mime_type);
            if (!$found) {
                throw new SiteInvalidImageException(
                    sprintf(
                        'The mime-type “%s” is not present in the ImageType ' .
                            'table.',
                        $mime_type
                    )
                );
            }

            $type = $image_type;
        } else {
            $type = $dimension->default_type;
        }

        return $type;
    }

    // }}}
    // {{{ protected function getDimensionBindingFileSize()

    /**
     * Gets the file size of a processed image dimension.
     *
     * This works around a upstream limitation of Imagick that does not allow
     * cloned objects to get the correct image size. See
     * @link{https://bugs.php.net/bug.php?id=64015}.
     *
     * @param SiteImageDimension $dimension the image's dimension.
     * @param SiteImageDimensionBinding $dimension_binding the image's
     *                                                     dimension binding.
     *
     * @return int The filesize in byte of the dimension binding.
     */
    protected function getDimensionBindingFileSize(
        SiteImageDimension $dimension,
        SiteImageDimensionBinding $dimension_binding
    ) {
        $file = $this->getFilePath($dimension->shortname);

        if (!file_exists($file)) {
            throw new SiteException(
                sprintf(
                    'Dimension ‘%s’ binding file not found at ‘%s’.',
                    $dimension->shortname,
                    $file
                )
            );
        }

        return filesize($file);
    }

    // }}}
    // {{{ protected function saveFile()

    /**
     * Saves the current image
     *
     * @param Imagick $imagick the imagick instance to work with.
     * @param SiteImageDimension $dimension the dimension to save.
     */
    protected function saveFile(Imagick $imagick, SiteImageDimension $dimension)
    {
        $imagick->setCompressionQuality($dimension->quality);

        if ($dimension->interlace) {
            $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        }

        if ($dimension->strip) {
            $imagick->stripImage();
        }

        // recursively create file directories
        $directory = $this->getFileDirectory($dimension->shortname);
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = $this->getFilePath($dimension->shortname);
        $imagick->writeImage($filename);
    }

    // }}}
    // {{{ protected function copyFile()

    /**
     * Copies the image
     *
     * @param string $image_file the image file to save
     * @param SiteImageDimension $dimension the dimension to save.
     */
    protected function copyFile($image_file, SiteImageDimension $dimension)
    {
        // recursively create file directories
        $directory = $this->getFileDirectory($dimension->shortname);
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = $this->getFilePath($dimension->shortname);
        copy($image_file, $filename);
    }

    // }}}
    // {{{ protected function getImagick()

    /**
     * Gets the Imagick object to process
     *
     * @param string $image_file the image file to process.
     * @param SiteImageDimension $dimension the dimension to process.
     */
    protected function getImagick($image_file, SiteImageDimension $dimension)
    {
        $imagick = null;

        $crop_box = $this->getCropBox($dimension);
        if ($crop_box === null) {
            // Use the smallest non-cropped processed image bigger than the
            // current dimension.
            foreach ($this->imagick_instances as $imagick_obj) {
                if (
                    $imagick_obj->getImageWidth() >= $dimension->max_width &&
                    $imagick_obj->getImageHeight() >= $dimension->max_height
                ) {
                    $imagick = $imagick_obj;
                }
            }
        }

        if ($imagick === null) {
            $imagick = $this->getNewImagick($image_file, $dimension);
        }

        return floatval(phpversion('imagick')) >= 3.1
            ? clone $imagick
            : $imagick->clone();
    }

    // }}}
    // {{{ protected function getNewImagick()

    /**
     * Gets a new Imagick instance from a file
     *
     * @param string $image_file the image file to process.
     * @param SiteImageDimension $dimension the dimension to process.
     */
    protected function getNewImagick($image_file, SiteImageDimension $dimension)
    {
        $crop_box = $this->getCropBox($dimension);
        $imagick = new Imagick();

        if (
            $crop_box === null &&
            $dimension->max_width !== null &&
            $dimension->max_height !== null
        ) {
            $imagick->setSize($dimension->max_width, $dimension->max_height);
        }

        try {
            $imagick->readImage($image_file);
        } catch (ImagickException $e) {
            throw new SiteInvalidImageException(
                $e->getMessage(),
                $e->getCode()
            );
        }

        return $imagick;
    }

    // }}}
    // {{{ protected function getOriginalImagick()

    /**
     * Gets a the Imagick instance for the original file
     */
    protected function getOriginalImagick()
    {
        return reset($this->imagick_instances);
    }

    // }}}
    // {{{ protected function getImageSet()

    protected function getImageSet()
    {
        if ($this->image_set instanceof SiteImageSet) {
            return $this->image_set;
        }

        if ($this->image_set_shortname == '') {
            throw new SwatException(
                'To process images, an image type ' .
                    'shortname must be defined in the image dataobject.'
            );
        }

        $class_name = SwatDBClassMap::get('SiteImageSet');

        $image_set = new $class_name();
        $image_set->setDatabase($this->db);

        if ($image_set->loadByShortname($this->image_set_shortname) === false) {
            throw new SwatException(
                sprintf(
                    'Image set “%s” does not exist.',
                    $this->image_set_shortname
                )
            );
        }

        $this->image_set = $image_set;

        return $this->image_set;
    }

    // }}}
    // {{{ protected function getResizeFilter()

    protected function getResizeFilter(SiteImageDimension $dimension)
    {
        if ($dimension->resize_filter === null) {
            $threshold = $this->getBoxFilterThreshold();

            if (
                $dimension->max_width >= $threshold &&
                $dimension->max_height >= $threshold
            ) {
                $filter = Imagick::FILTER_BOX;
            } else {
                $filter = Imagick::FILTER_LANCZOS;
            }
        } else {
            $filter = constant('Imagick::' . $dimension->resize_filter);

            if ($filter === null) {
                throw new SwatException(
                    sprintf(
                        '%s is not a valid ' . 'Imagick constant.',
                        $dimension->resize_filter
                    )
                );
            }
        }

        return $filter;
    }

    // }}}
    // {{{ protected function getBoxFilterThreshold()

    /**
     * Get the threshold for using the BOX filter
     *
     * If the longest image dimension is greater than or equal to this
     * threshold, the Imagick::FILTER_BOX will be used as it is more efficient
     * than the default Imagick::FILTER_LANCZOS filter with little noticeable
     * difference for larger images.
     *
     * @return integer Threshold to use the BOX filter.
     */
    protected function getBoxFilterThreshold()
    {
        return 350;
    }

    // }}}
    // {{{ protected function getCropBox()

    /**
     * Get an optional crop box to use on imput image before processing
     *
     * @param SiteImageDimension $dimension Dimension to check for crop box
     *
     * @return array a bounding-box array
     */
    protected function getCropBox(SiteImageDimension $dimension)
    {
        $crop_box = null;

        if (isset($this->crop_boxes[$dimension->shortname])) {
            $crop_box = $this->crop_boxes[$dimension->shortname];
        } elseif ($this->crop_box !== null) {
            $crop_box = $this->crop_box;
        }

        return $crop_box;
    }

    // }}}
    // {{{ protected function prepareForProcessing()

    protected function prepareForProcessing($image_file)
    {
        $wrapper = $this->getImageDimensionBindingWrapperClassName();
        $this->dimension_bindings = new $wrapper();

        if ($this->original_filename == '') {
            // extra space is to overcome a UTF-8 problem with basename
            // strtok is to drop any query string parameters when original
            // file is loaded via a url.
            $this->original_filename = strtok(
                ltrim(basename(' ' . $image_file)),
                '?'
            );
        }

        if ($this->filename == '' && $this->getImageSet()->obfuscate_filename) {
            $this->filename = sha1(uniqid(rand(), true));
        }

        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            throw new SwatException(
                'Class Imagick from extension imagick > 2.0.0 not found.'
            );
        }
    }

    // }}}
    // {{{ protected function queueCdnTask()

    /**
     * Queues a CDN task to be preformed later
     *
     * @param string $operation the operation to preform
     * @param SiteImageDimension $dimension the image dimension we're queuing
     *                                       the action for.
     */
    protected function queueCdnTask(
        $operation,
        SiteImageDimension $dimension = null
    ) {
        $class_name = SwatDBClassMap::get('SiteImageCdnTask');

        $task = new $class_name();
        $task->setDatabase($this->db);
        $task->operation = $operation;

        if ($operation == 'copy' || $operation == 'update') {
            $task->image = $this;
            $task->dimension = $dimension;
        } else {
            $task->file_path = $this->getUriSuffix($dimension->shortname);
        }

        $task->save();
    }

    // }}}
}
