<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Support got image manipulation class using {@link  http://imagemagick.org ImageMagick}.
 *
 * @package    Kohana/Image
 * @category   Drivers
 * @author     Joel Pittet joel@pittet.ca
 * @author     Javier Aranda <internet@javierav.com>
 * @license    http://kohanaphp.com/license
 */
class Kohana_Image_ImageMagick extends Image {

	/**
	 * @var  string  path to ImageMagick binaries
	 */
	protected static $_imagemagick;

	/**
	 * @var  string  temporary image file
	 */
	protected $filetmp;

	/**
	 * Checks if ImageMagick is enabled and bundled. Bundled GD is required for some
	 * methods to work.
	 *
	 * @throws  Kohana_Exception
	 * @return  boolean
	 */
	public static function check()
	{
		exec(Image_ImageMagick::get_command('convert'), $response, $status);

		if ($status)
		{
			throw new Kohana_Exception('ImageMagick is not installed in :path, check your configuration. status :status', 
			array(':path'=>Image_ImageMagick::$_imagemagick,
			':status'=>$status)
			);
		}

		return Image_ImageMagick::$_checked = TRUE;
	}

	/**
	 * Runs [Image_ImageMagick::check] and loads the image.
	 *
	 * @return  void
	 * @throws  Kohana_Exception
	 */
	public function __construct($file)
	{
		// Load ImageMagick path from config
		Image_ImageMagick::$_imagemagick = Kohana::Config('imagemagick')->path;

		if (! is_dir(Image_ImageMagick::$_imagemagick))
		{
			throw new Kohana_Exception('ImageMagick path is not a valid directory, check your configuration');
		}

		if ( ! Image_ImageMagick::$_checked)
		{
			// Run the install check
			Image_ImageMagick::check();
		}

		parent::__construct($file);
	}

	/**
	 * Destroys the loaded image to free up resources.
	 *
	 * @return  void
	 */
	public function __destruct()
	{
		if (isset($this->filetmp) && file_exists($this->filetmp))
		{
			// Free all resources
			unlink($this->filetmp);
		}
	}

	protected function _do_resize($width, $height)
	{
		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		// Create a temporary file to store the new image
		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= ' -quality 100 -geometry '.escapeshellarg($width).'x'.escapeshellarg($height).'\!';
		$command .= ' '.escapeshellarg($fileout);

		exec($command, $response, $status);

		if ( ! $status )
		{
			// Delete old tmp file if exist
			if (isset($this->filetmp) && file_exists($this->filetmp))
			{
				unlink($this->filetmp);
			}

			// Update image data
			$this->filetmp = $fileout;
			$this->width = $width;
			$this->height = $height;

			return TRUE;
		}

		return FALSE;
	}

	protected function _do_crop($width, $height, $offset_x, $offset_y)
	{
		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		// Create a temporary file to store the new image
		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= ' -quality 100 -crop '.escapeshellarg($width).'x'.escapeshellarg($height).'+'.escapeshellarg($offset_x).'+'.escapeshellarg($offset_y);
		$command .= ' '.escapeshellarg($fileout);

		exec($command, $response, $status);

		if ( ! $status )
		{
			// Delete old tmp file if exist
			if (isset($this->filetmp) && file_exists($this->filetmp))
			{
				unlink($this->filetmp);
			}

			// Get the image information
			$info = $this->get_info($fileout);

			// Update image data
			$this->filetmp = $fileout;
			$this->width = $info->width;
			$this->height = $info->height;

			return TRUE;
		}

		return FALSE;
	}

	protected function _do_rotate($degrees)
	{
		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		// Create a temporary file to store the new image
		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= ' -quality 100 -matte -background none -rotate '.escapeshellarg($degrees);
		$command .= ' '.escapeshellarg('PNG:'.$fileout); // Save as PNG for transparency

		exec($command, $response, $status);

		if ( ! $status )
		{
			// Delete old tmp file if exist
			if (isset($this->filetmp) && file_exists($this->filetmp))
			{
				unlink($this->filetmp);
			}

			// Get the image information
			$info = $this->get_info($fileout);

			// Update image data
			$this->filetmp = $fileout;
			$this->width = $info->width;
			$this->height = $info->height;
			$this->type = $info->type;
			$this->mime = $info->mime;

			return TRUE;
		}

		return FALSE;
	}

	protected function _do_flip($direction)
	{
		$flip_command = ($direction === Image::HORIZONTAL) ? '-flop': '-flip';

		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		// Create a temporary file to store the new image
		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= ' -quality 100 '.$flip_command;
		$command .= ' '.escapeshellarg($fileout);

		exec($command, $response, $status);

		if ( ! $status )
		{
			// Delete old tmp file if exist
			if (isset($this->filetmp) && file_exists($this->filetmp))
			{
				unlink($this->filetmp);
			}

			// Update image data
			$this->filetmp = $fileout;

			return TRUE;
		}

		return FALSE;
	}

	protected function _do_sharpen($amount)
	{
		//IM not support $amount under 5 (0.15)
		$amount = ($amount < 5) ? 5 : $amount;

		// Amount should be in the range of 0.0 to 3.0
		$amount = ($amount * 3.0) / 100;

		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		// Create a temporary file to store the new image
		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= ' -quality 100 -sharpen 0x'.$amount;
		$command .= ' '.escapeshellarg($fileout);

		exec($command, $response, $status);

		if ( ! $status )
		{
			// Delete old tmp file if exist
			if (isset($this->filetmp) && file_exists($this->filetmp))
			{
				unlink($this->filetmp);
			}

			// Get the image information
			$info = $this->get_info($fileout);

			// Update image data
			$this->filetmp = $fileout;
			$this->width = $info->width;
			$this->height = $info->height;

			return TRUE;
		}

		return FALSE;
	}

	protected function _do_reflection($height, $opacity, $fade_in)
	{
		// Convert an opacity range of 0-100 to 255-0
	$opacity = round(abs(($opacity * 255 / 100)));

		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		// Create the reflect image from current image
		$reflect_image = Image::factory($filein, 'ImageMagick');

		// Crop the image to $height of reflect starting by bottom
		$reflect_image->crop($reflect_image->width, $height, 0, true);

		// Flip the reflect image vertically
		$reflect_image->flip(Image::VERTICAL);

		// Create alpha channel
		$alpha = tempnam(Upload::$default_directory, '');

		$gradient = ($fade_in) ? "rgb(0,0,0)-rgb($opacity,$opacity,$opacity)" : "rgb($opacity,$opacity,$opacity)-rgb(0,0,0)";

		$command = Image_ImageMagick::get_command('convert');
		$command .= ' -quality 100 -size '.escapeshellarg($this->width).'x'.escapeshellarg($height).' gradient:'.escapeshellarg($gradient);
		$command .= ' '.escapeshellarg('PNG:'.$alpha);

		exec($command, $response, $status);

		if ($status)
		{
			return FALSE;
		}

		// Apply alpha channel
		$tmpfile = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($reflect_image->get_file_path()).' '.escapeshellarg($alpha);
		$command .= ' -quality 100 -alpha Off -compose Copy_Opacity -composite';
		$command .= ' '.escapeshellarg('PNG:'.$tmpfile);

		exec($command, $response, $status);

		if ($status)
		{
			return FALSE;
		}

		// Merge image with their reflex
		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein).' '.escapeshellarg($tmpfile);
		$command .= ' -quality 100 -append ';
		$command .= ' '.escapeshellarg('PNG:'.$fileout); //save as PNG to keep transparency

		exec($command, $response, $status);

		if ($status)
		{
			return FALSE;
		}

		//delete temporary images
		unset($reflect_image);
		unlink($alpha);
		unlink($tmpfile);

		// Delete old tmp file if exist
		if (isset($this->filetmp) && file_exists($this->filetmp))
		{
			unlink($this->filetmp);
		}

		// Get the image information
		$info = $this->get_info($fileout);

		// Update image data
		$this->filetmp = $fileout;
		$this->width = $info->width;
		$this->height = $info->height;

		return TRUE;
	}

	protected function _do_watermark(Image $image, $offset_x, $offset_y, $opacity)
	{
		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		// Create temporary file to store the watermark image
		$watermark = tempnam(Upload::$default_directory, '');
		$fp = fopen($watermark, 'wb');

		if ( ! fwrite($fp, $image->render()))
		{
			return FALSE;
		}

		// Merge watermark with image
		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('composite');
		$command .= ' -quality 100 -dissolve '.escapeshellarg($opacity).'% -geometry +'.escapeshellarg($offset_x).'+'.escapeshellarg($offset_y);
		$command .= ' '.escapeshellarg($watermark).' '.escapeshellarg($filein);
		$command .= ' '.escapeshellarg('PNG:'.$fileout); //save as PNG to keep transparency

		exec($command, $response, $status);

		if ($status)
		{
			return FALSE;
		}

		// Delete temp files and close handlers
		fclose($fp);
		unlink($watermark);

		// Delete old tmp file if exist
		if (isset($this->filetmp) && file_exists($this->filetmp))
		{
			unlink($this->filetmp);
		}

		// Update image data
		$this->filetmp = $fileout;

		return TRUE;
	}

	protected function _do_background($r, $g, $b, $opacity)
	{
		$opacity = $opacity / 100;

		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= " -quality 100 -background ".escapeshellarg("rgba($r, $g, $b, $opacity)").' -flatten';
		$command .= ' '.escapeshellarg('PNG:'.$fileout);

		exec($command, $response, $status);

		if ( ! $status )
		{
			// Delete old tmp file if exist
			if (isset($this->filetmp) && file_exists($this->filetmp))
			{
				unlink($this->filetmp);
			}

			// Get the image information
			$info = $this->get_info($fileout);

			// Update image data
			$this->filetmp = $fileout;
			$this->width = $info->width;
			$this->height = $info->height;

			return TRUE;
		}

		return FALSE;
	}

	protected function _do_save($file, $quality)
	{
		// If tmp image file not exist, use original
		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= (isset($quality)) ? ' -quality '.escapeshellarg($quality) : '';
		$command .= ' '.escapeshellarg($file);

		exec($command, $response, $status);

		if ( ! $status )
		{
			return TRUE;
		}

		return FALSE;
	}

	protected function _do_render($type, $quality)
	{
		$tmpfile = tempnam(Upload::$default_directory, '');

		// If tmp image file not exist, use original
		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= (isset($quality)) ? ' -quality '.escapeshellarg($quality) : '';
		$command .= ' '.escapeshellarg(strtoupper($type).':'.$tmpfile);

		exec($command, $response, $status);

		if ( ! $status)
		{
			// Capture the output
			ob_start();

			readfile($tmpfile);

			// Delete tmp file
			unlink($tmpfile);

			return ob_get_clean();
		}

		return FALSE;
	}

	/**
	 * Return a specific command for the current OS
	 *
	 * @param   string  command
	 * @return  string  command translated for current OS
	 */
	protected static function get_command($command)
	{

		$command = Image_ImageMagick::$_imagemagick.DIRECTORY_SEPARATOR.$command;

		return (Kohana::$is_windows) ? $command.'.exe' : $command;
	}

	/**
	 * Get and return file info
	 *
	 * @param   string	path to file
	 * @throws  Kohana_Exception
	 * @return  object  file info
	 */
	protected function get_info($file)
	{
		try
		{
			// Get the real path to the file
			$file = realpath($file);

			// Get the image information
			$info = getimagesize($file);
		}
		catch (Exception $e)
		{
			// Ignore all errors while reading the image
		}

		if (empty($file) OR empty($info))
		{
			throw new Kohana_Exception('Not an image or invalid image: :file',
					array(':file' => Kohana::debug_path($file)));
		}

		$return = new stdClass();

		$return->file   = $file;
		$return->width  = $info[0];
		$return->height = $info[1];
		$return->type   = $info[2];
		$return->mime   = image_type_to_mime_type($return->type);

		return $return;
	}

	/**
	 * Get the file path
	 * 
	 * @return string file path
	 */
	public function get_file_path()
	{
		return $this->filetmp;
	}




	/**
	 * Create an empty image with the given width and height.
	 *
	 * @param   integer   image width
	 * @param   integer   image height
	 * @return  resource
	 */
	protected function _create($width, $height)
	{
		
		$filein = (isset($this->filetmp)) ? $this->filetmp : $this->file;

		$fileout = tempnam(Upload::$default_directory, '');

		$command = Image_ImageMagick::get_command('convert').' '.escapeshellarg($filein);
		$command .= ' -quality 100 -size '.escapeshellarg($width).'x'.escapeshellarg($height).' -flatten';
		$command .= ' '.escapeshellarg('PNG:'.$fileout);

		exec($command, $response, $status);

		if ( ! $status )
		{
			// Delete old tmp file if exist
			if (isset($this->filetmp) && file_exists($this->filetmp))
			{
				unlink($this->filetmp);
			}

			// Get the image information
			$info = $this->get_info($fileout);

			// Update image data
			$this->filetmp = $fileout;
			$this->width = $info->width;
			$this->height = $info->height;

			return TRUE;
		}

		return FALSE;
	}

} // End Kohana_Image_ImageMagic