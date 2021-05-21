<?php

ini_set("max_execution_time", 10000);


function Mod($a, $b)
{
	return ($a % $b + $b) % $b;
}

function Vigenere_Cipher($input, $key, $encipher)
{
	$keyLen = strlen($key);

	for ($i = 0; $i < $keyLen; ++$i)
		if (!ctype_alpha($key[$i]))
			return ""; // Error

	$output = "";
	$nonAlphaCharCount = 0;
	$inputLen = strlen($input);

	for ($i = 0; $i < $inputLen; ++$i) {
		if (ctype_alpha($input[$i])) {
			$cIsUpper = ctype_upper($input[$i]);
			$offset = ord($cIsUpper ? 'A' : 'a');
			$keyIndex = ($i - $nonAlphaCharCount) % $keyLen;
			$k = ord($cIsUpper ? strtoupper($key[$keyIndex]) : strtolower($key[$keyIndex])) - $offset;
			$k = $encipher ? $k : -$k;
			$ch = chr((Mod(((ord($input[$i]) + $k) - $offset), 26)) + $offset);
			$output .= $ch;
		} else {
			$output .= $input[$i];
			++$nonAlphaCharCount;
		}
	}

	return $output;
}

function Encode_vigenere($input, $key)
{
	return Vigenere_Cipher($input, $key, true);
}

function Decode_vigenere($input, $key)
{
	return Vigenere_Cipher($input, $key, false);
}



function is_even($num)
{
	// returns true if $num is even, false if not
	return ($num % 2 == 0);
}

function asc2bin($char)
{
	// returns 8bit binary value from ASCII char
	// eg; asc2bin("a") returns 01100001
	return str_pad(decbin(ord($char)), 8, "0", STR_PAD_LEFT);
}

function bin2asc($bin)
{
	// returns ASCII char from 8bit binary value
	// eg; bin2asc("01100001") returns a
	// argument MUST be sent as string
	return chr(bindec($bin));
}

function rgb2bin($rgb)
{
	// returns binary from rgb value (according to evenness)
	// this way, we can store one ascii char in 2.6 pixels
	// not a great ratio, but it works (albeit slowly)

	$binstream = "";
	$red = ($rgb >> 16) & 0xFF;
	$green = ($rgb >> 8) & 0xFF;
	$blue = $rgb & 0xFF;

	if (is_even($red)) {
		$binstream .= "1";
	} else {
		$binstream .= "0";
	}
	if (is_even($green)) {
		$binstream .= "1";
	} else {
		$binstream .= "0";
	}
	if (is_even($blue)) {
		$binstream .= "1";
	} else {
		$binstream .= "0";
	}

	return $binstream;
}

function steg_hide($maskfile, $hidefile)
{
	// hides $hidefile in $maskfile

	// initialise some vars
	$binstream = "";
	$recordstream = "";
	$make_odd = array();

	// create images
	$pic = ImageCreateFromJPEG($maskfile['tmp_name']);
	$attributes = getImageSize($maskfile['tmp_name']);
	$outpic = ImageCreateFromJPEG($maskfile['tmp_name']);

	if (!$pic || !$outpic || !$attributes) {
		// image creation failed
		return "cannot create images - maybe GDlib not installed?";
	}

	// read file to be hidden
	$data = $hidefile;

	// generate unique boundary that does not occur in $data
	// 1 in 16581375 chance of a file containing all possible 3 ASCII char sequences
	// 1 in every ~1.65 billion files will not be steganographisable by this script
	// though my maths might be wrong.
	// if you really want to get silly, add another 3 random chars. (1 in 274941996890625)
	// ^^^^^^^^^^^^ would require appropriate modification to decoder.
	$boundary = "";
	do {
		$boundary .= chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255));
	} while (strpos($data, $boundary) !== false && strpos('rahasia.txt', $boundary) !== false);

	// add boundary to data
	$data = $boundary . 'rahasia.txt' . $boundary . $data . $boundary;
	// you could add all sorts of other info here (eg IP of encoder, date/time encoded, etc, etc)
	// decoder reads first boundary, then carries on reading until boundary encountered again
	// saves that as filename, and carries on again until final boundary reached

	// check that $data will fit in maskfile
	if (strlen($data) * 8 > ($attributes[0] * $attributes[1]) * 3) {
		// remove images
		ImageDestroy($outpic);
		ImageDestroy($pic);
		return "Cannot fit " . 'rahasia.txt' . " in " . $maskfile['name'] . ".<br />" . "rahasia.txt" . " requires mask to contain at least " . (intval((strlen($data) * 8) / 3) + 1) . " pixels.<br />Maximum filesize that " . $maskfile['name'] . " can hide is " . intval((($attributes[0] * $attributes[1]) * 3) / 8) . " bytes";
	}

	// convert $data into array of true/false
	// pixels in mask are made odd if true, even if false
	for ($i = 0; $i < strlen($data); $i++) {
		// get 8bit binary representation of each char
		$char = $data{
			$i};
		$binary = asc2bin($char);

		// save binary to string
		$binstream .= $binary;

		// create array of true/false for each bit. confusingly, 0=true, 1=false
		for ($j = 0; $j < strlen($binary); $j++) {
			$binpart = $binary{
				$j};
			if ($binpart == "0") {
				$make_odd[] = true;
			} else {
				$make_odd[] = false;
			}
		}
	}

	// now loop through each pixel and modify colour values according to $make_odd array
	$y = 0;
	for ($i = 0, $x = 0; $i < sizeof($make_odd); $i += 3, $x++) {
		// read RGB of pixel
		$rgb = ImageColorAt($pic, $x, $y);
		$cols = array();
		$cols[] = ($rgb >> 16) & 0xFF;
		$cols[] = ($rgb >> 8) & 0xFF;
		$cols[] = $rgb & 0xFF;

		for ($j = 0; $j < sizeof($cols); $j++) {
			if ($make_odd[$i + $j] === true && is_even($cols[$j])) {
				// is even, should be odd
				$cols[$j]++;
			} else if ($make_odd[$i + $j] === false && !is_even($cols[$j])) {
				// is odd, should be even
				$cols[$j]--;
			} // else colour is fine as is
		}

		// modify pixel
		$temp_col = ImageColorAllocate($outpic, $cols[0], $cols[1], $cols[2]);
		ImageSetPixel($outpic, $x, $y, $temp_col);

		// if at end of X, move down and start at x=0
		if ($x == ($attributes[0] - 1)) {
			$y++;
			// $x++ on next loop converts x to 0
			$x = -1;
		}
	}

	// output modified image as PNG (or other *LOSSLESS* format)
	$nama_gambar = rand(1000, 10000) . "encoded.jpeg";
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=$nama_gambar");
	header('Content-Transfer-Encoding: binary');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	ImagePNG($outpic);

	// remove images
	ImageDestroy($outpic);
	ImageDestroy($pic);
	exit();
}

function steg_recover($gambar)
{
	// recovers file hidden in a PNG image

	$binstream = "";
	$filename = "";

	// get image and width/height
	$attributes = getImageSize($gambar['tmp_name']);
	$pic = ImageCreateFromPNG($gambar['tmp_name']);

	if (!$pic || !$attributes) {
		return "could not read image";
	}

	// get boundary
	$bin_boundary = "";
	$boundary = "";
	for ($x = 0; $x < 8; $x++) {
		$bin_boundary .= rgb2bin(ImageColorAt($pic, $x, 0));
	}

	// convert boundary to ascii
	for ($i = 0; $i < strlen($bin_boundary); $i += 8) {
		$binchunk = substr($bin_boundary, $i, 8);
		$boundary .= bin2asc($binchunk);
	}


	// now convert RGB of each pixel into binary, stopping when we see $boundary again

	// do not process first boundary
	$start_x = 8;
	$ascii = "";
	for ($y = 0; $y < $attributes[1]; $y++) {
		for ($x = $start_x; $x < $attributes[0]; $x++) {
			// generate binary
			$binstream .= rgb2bin(ImageColorAt($pic, $x, $y));
			// convert to ascii
			if (strlen($binstream) >= 8) {
				$binchar = substr($binstream, 0, 8);
				$ascii .= bin2asc($binchar);
				$binstream = substr($binstream, 8);
			}

			// test for boundary
			if (strpos($ascii, $boundary) !== false) {
				// remove boundary
				$ascii = substr($ascii, 0, strlen($ascii) - 3);

				if (empty($filename)) {
					$filename = $ascii;
					$ascii = "";
				} else {
					// final boundary; exit both 'for' loops
					break 2;
				}
			}
		}
		// on second line of pixels or greater; we can start at x=0 now
		$start_x = 0;
	}

	// remove image from memory
	ImageDestroy($pic);

	/* and output result (retaining original filename)
	header("Content-type: text/plain");
	header("Content-Disposition: attachment; filename=".$filename);*/
	return $ascii;
}

if (!empty($_POST['secret'])) {
	// ensure a readable mask file has been sent
	$extension = strtolower(substr($_FILES['maskfile']['name'], -3));
	if ($extension == "jpg") {
		// esnkripsi Vigenere
		$tes = $_POST['key'];
		$key = strtoupper($tes);
		$plaintext = $_POST['secret'];
		$plaintext = strtoupper($plaintext);

		$ciphertextVigenere = Encode_vigenere($plaintext, $key);
		//$decrypted = rc4( $key, $ciphertext );

		// enskripsi base64
		$base64 = base64_encode($ciphertextVigenere);
		steg_hide($_FILES['maskfile'], $base64);
	} else {
		$result = "Only .jpg mask files are supported";
		echo $result;
	}
}

?>
<html>

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta property='og:image' content='http://security.cs.umass.edu/cyber-biglock.jpg' />
	<title>Kripto Stegano</title>

	<!-- Tell the browser to be responsive to screen width -->
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<!-- Bootstrap 3.3.6 -->
	<link rel="stylesheet" href="css/bootstrap.min.css">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css">
	<!-- Ionicons -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">
	<!-- Theme style -->
	<link rel="stylesheet" href="css/AdminLTE.min.css">



</head>

<body class="hold-transition login-page" style="background:black;">

	<div class="col-xs-12 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3" style="background:black; margin-bottom:50px;">

		<!-- /.login-logo -->
		<div class="login-box-body" style="background:black;" ;">
			<div class="nav-tabs-custom">

				<ul class="nav nav-tabs pull-right" style="background-color:#95a5a6; ">
					<li><a href="#tab_1" class="btn btn-app" data-toggle="tab" style="width: 297;"><i class="fa fa-unlock"></i> Dekripsi</a> </li>
					<li class="active"><a style="width: 297;" href="#tab_2" class="btn btn-app" data-toggle="tab"><i class="fa fa-lock"></i> Enkripsi</a></li>
				</ul>

				<?php
				if (!empty($_FILES['gambar']['tmp_name'])) {
					$result = steg_recover($_FILES['gambar']);

					// decode base 64
					$base64 = base64_decode($result);

					// decode vigenere
					$key = $_POST['key_deskripsi'];
					$key = strtoupper($key);
					$plaintext = Decode_vigenere($base64, $key);

					echo "
					<h2 align=center>Hasil</h2>
		<table border=0 class='table table-bordered' style='font-size:large'>

			<tr>
				<td align=center><b>Chipertext Base64:</b></td>
			</tr>

			<tr>
				<td align=center><textarea class='form-control'>$result</textarea></td>
			</tr>

			<tr>
				<td align=center><b>Key:</b></td>
			</tr>

			<tr>
				<td align=center><textarea class='form-control'>$key</textarea></td>
			</tr>

			<tr>
				<td align=center><b>Chipertext Vigenere:</b></td>
			</tr>	

			<tr>
				<td align=center><textarea class='form-control'>$base64</textarea></td>
			</tr>	

			<tr>
				<td align=center><b>Plaintext:</b></td>
			</tr>

			<tr>
				<td align=center><textarea class='form-control'> $plaintext </textarea> </td>
			</tr>
		</table>
	";
				}
				?>

				<div class="tab-content">
					<div class="tab-pane" id="tab_1">
						<h2 align=center>Dekripsi</h2>
						<form action="<?php $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data">
							<div class="form-group has-feedback">
								<input type="text" name="key_deskripsi" id="key_deskripsi" class="form-control" placeholder="Input Key" required>
								<span class="fa fa-key form-control-feedback"></span>
							</div>
							<label>Stego Image (jpeg): </label>
							<div class="input-group" style="margin-bottom:30px">
								<span class="input-group-addon"><i class="fa fa-image"></i></span>
								<input type="file" class="form-control" accept="image/*" name="gambar" id="gambar" required>
							</div>
							<div class="row">
								<!-- /.col -->
								<div class="col-xs-12">
									<button type="submit" class="btn btn-primary btn-flat pull-right">Submit &nbsp</i></button>
								</div>
								<!-- /.col -->
							</div>
						</form>
					</div>
					<!-- /.tab-pane -->
					<div class="tab-pane active" id="tab_2">
						<h2 align=center>Enkripsi</h2>
						<form id="form_stegano" action="<?php $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data">
							<div class="form-group has-feedback">
								<input type="text" id="key_enskripsi" name="key" class="form-control" placeholder="Input Key">
								<span class="fa fa-key form-control-feedback"></span>
							</div>
							<div class="form-group has-feedback">
								<textarea id="secret" name="secret" class="form-control" rows=3 placeholder="Input Plaintext" required></textarea>
								<span class="fa fa-file-text-o form-control-feedback"></span>
							</div>
							<label>Cover Image (jpg): </label>
							<div class="input-group" style="margin-bottom:30px">
								<span class="input-group-addon"><i class="fa fa-image"></i></span>
								<input type="file" class="form-control" accept="image/jpeg" name="maskfile" required>
							</div>
							<div class="row">
								<!-- /.col -->
								<div class="col-xs-12">
									<button type="submit" class="btn btn-primary btn-flat pull-right">Submit &nbsp </i></button>
								</div>
								<!-- /.col -->
							</div>
						</form>
					</div>
					<!-- /.tab-pane -->
				</div>
				<!-- /.tab-content -->

			</div>
		</div>

	</div>
	<!-- /.login-box -->
	<!-- jQuery 2.2.3 -->
	<script src="js/jquery-2.2.3.min.js"></script>
	<!-- Bootstrap 3.3.6 -->
	<script src="js/bootstrap.min.js"></script>

</body>

</html>