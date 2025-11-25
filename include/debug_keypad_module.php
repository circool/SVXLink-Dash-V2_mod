<?php
/**
 * @file debug_keypad_module.php
 * @version 1.0.3
 * @date 2021-11-24
 * @author vladimir@tsurkanenko.ru
 * @author Firstname Lastname@tsurkanenko
 */
$url = $_SERVER['REQUEST_URI'] . "/include";
// header("Refresh: 10; URL=$url");
// Defined buttons:


if (isset($_POST['info'])) {
	shell_exec('echo "*#" > /var/run/svxlink/dtmf_svx');
}

if (isset($_POST['keypad_1'])) {
	shell_exec('echo "1" > /var/run/svxlink/dtmf_svx');
}

if (isset($_POST['keypad_2'])) {
	shell_exec('echo "2" > /var/run/svxlink/dtmf_svx');
}

if (isset($_POST['keypad3'])) {
	shell_exec('echo "3" > /var/run/svxlink/dtmf_svx');
}
if (isset($_POST['keypad_4'])) {
	shell_exec('echo "4" > /var/run/svxlink/dtmf_svx');
}


if (isset($_POST['keypad_5'])) {
	shell_exec('echo "5" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 0</center></h1></p></pre>';
}

if (isset($_POST['keypad_6'])) {
	shell_exec('echo "6" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 1</center></h1></p></pre>';
}

if (isset($_POST['keypad_7'])) {
	shell_exec('echo "7" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 2</center></h1></p></pre>';
}

if (isset($_POST['keypad_8'])) {
	shell_exec('echo "8" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 3</center></h1></p></pre>';
}

if (isset($_POST['keypad_9'])) {
	shell_exec('echo "9" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 4</center></h1></p></pre>';
}

if (isset($_POST['keypad_0'])) {
	shell_exec('echo "0" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 5</center></h1></p></pre>';
}

if (isset($_POST['keypad_*'])) {
	shell_exec('echo "*" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 6</center></h1></p></pre>';
}

if (isset($_POST['keypad_#'])) {
	shell_exec('echo "#" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 7</center></h1></p></pre>';
}

if (isset($_POST['keypad_disconnect'])) {
	shell_exec('echo "#" > /var/run/svxlink/dtmf_svx');
	// echo '<pre><h1><center><p style="color: #454545; ">Send DTMF: 8</center></h1></p></pre>';
}
?>
<form method="post">
	<div class="mode_flex">
		<div class="mode_flex row">
			<div class="mode_flex column">
				<div class="divTableHead"><?php echo getTranslation($lang, 'DTMF'); ?></div>
			</div>
		</div>
		<div>
			<div class="mode_flex" style="background: #949494;">
				<div class="mode_flex row center">

					<div class="mode_flex column" title="TODO"><button name="keypad_1">1</button></div>
					<div class="mode_flex column" title="TODO"><button name="keypad_2">2</button></div>
					<div class="mode_flex column" title="TODO"><button name="keypad_3">3</button></div>
				</div>
				<div class="mode_flex row center">
					<div class="mode_flex column" title="TODO"><button name="keypad_4">4</button></div>
					<div class="mode_flex column" title="TODO"><button name="keypad_5">5</button></div>
					<div class="mode_flex column" title="TODO"><button name="keypad_6">6</button>
					</div>
				</div>
				<div class="mode_flex row center">
					<div class="mode_flex column" title="TODO"><button name="keypad_7">7</button></div>
					<div class="mode_flex column" title="TODO"><button name="keypad_8">8</button></div>
					<div class="mode_flex column" title="TODO"><button name="keypad_9">9</button></div>
				</div>
				<div class="mode_flex row center">
					<div class="mode_flex column" title="TODO"><button name="keypad_*">*</button></div>
					<div class=" mode_flex column" title="TODO"><button name="keypad_0">0</button></div>
					<div class=" mode_flex column" title="TODO"><button name="keypad_#">#</button></div>
				</div>
				<div class=" mode_flex row">
					<div class="mode_flex column center" title=" TODO"><button name="keypad_disconnect">disconnect</button></div>
				</div>
			</div>
		</div>
	</div>
</form>