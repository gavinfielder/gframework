#!/usr/bin/php
<?php
# **************************************************************************** #
#                                                                              #
#                                                         :::      ::::::::    #
#    setup.php                                          :+:      :+:    :+:    #
#                                                     +:+ +:+         +:+      #
#    By: gfielder <marvin@42.fr>                    +#+  +:+       +#+         #
#                                                 +#+#+#+#+#+   +#+            #
#    Created: 2019/04/04 15:39:18 by gfielder          #+#    #+#              #
#    Updated: 2019/04/04 15:39:18 by gfielder         ###   ########.fr        #
#                                                                              #
# **************************************************************************** #

define("ERR", "\x1B[1;31m");
define("RST", "\x1B[0;0;0m");
define("GRN", "\x1B[0;36m");
define("PMT", "\x1B[4;36m");

function get_input_nospaces()
{
	while (1)
	{
		$input = trim(readline());
		if ($input == null || $input == "" || preg_match("/\s/", $input))
			echo ERR."Invalid. Please enter a nonempty string with no spaces.\n".RST;
		else
			return $input;
	}
}

function get_input_nonempty()
{
	while (1)
	{
		$input = trim(readline());
		if ($input == null || $input == "")
			echo ERR."Invalid. Please enter a nonempty string.\n".RST;
		else
			return $input;
	}
}


function get_input_or_empty_string()
{
	while (1)
	{
		$input = trim(readline());
		if ($input == null || $input == "")
			return "";
		elseif (preg_match("/\s/", $input))
				echo ERR."Invalid. Please enter a string with no spaces, or empty to keep default.\n".RST;
		else
			return $input;
	}
}

function get_libfilename($default)
{
	while (1)
	{
		$libfilename = get_input_or_empty_string();
		if ($libfilename == "")
			return $default;
		elseif (preg_match("/lib(.*?)\.a$/", $libfilename, $matches))
			return $matches[1];
		else
			echo ERR."Invalid library name. Static libraries are prefixed with lib- and end in .a\n".RST;
	}
}

function validate_repo_url($url)
{
	$msg = trim(shell_exec("git ls-remote --exit-code -h $url 2>&1"));
	if (preg_match("/fatal:/", $msg))
		return false;
	return true;
}

function get_valid_repo()
{
	while (1)
	{
		$url = str_replace(array('\'', '"', ',', ';', '<', '>'), "",
			trim(readline(), "\t\n\r\0\x0B '\"?"));
		if (validate_repo_url($url) == false)
			echo ERR."That does not appear to be a valid repository. Try again.\n".RST;
		else
			return $url;
	}
}

function get_valid_repo_or_empty_string()
{
	while (1)
	{
		$input = str_replace(array('\'', '"', ',', ';', '<', '>'), "",
			trim(readline(), "\t\n\r\0\x0B '\"?"));
		if ($input == null || $input == "")
			return "";
		elseif (validate_repo_url($input))
			return $input;
		else
			echo ERR."That does not appear to be a valid repository. Enter a valid repo or leave blank.\n".RST;
	}
}

function get_yes_no()
{
	while (1)
	{
		$input = trim(readline());
		if (strtolower($input) == 'y' || strtolower($input) == 'yes')
			return true;
		elseif (strtolower($input) == 'n' || strtolower($input) == 'no')
			return false;
		echo ERR."Invalid response. Please type y/Y/yes or n/N/no\n".RST;
	}
}


# -----------------------------------------------------------------------------
#   Phase 1: Get Basic Details
# -----------------------------------------------------------------------------

//Get the name of the project
echo PMT."Enter the project name:\n".RST;
$project_name = get_input_nospaces();
$pwd = shell_exec("pwd");
echo GRN."Ok, the project directory will be $pwd/$project_name/\n".RST;

//Get the repo url of the project
echo PMT."Enter the project repo url (or leave blank if not yet set):\n".RST;
$input = get_valid_repo_or_empty_string();
if ($input != "")
{
	echo GRN."Cloning repo... ".RST;
	system("git clone $input $project_name > /dev/null 2>&1 0>&1");
	echo GRN."done.\n".RST;
}
else
{
	echo GRN."Ok, the repo will not be set yet.\n".RST;
	mkdir($project_name);
}

chdir($project_name);

# -----------------------------------------------------------------------------
#   Phase 2: Determine Language
# -----------------------------------------------------------------------------

$using_c = false;
//Get language of project
echo PMT."Enter the language you will use for this project:\n".RST;
$input = get_input_nonempty();
echo GRN."$input is a good choice.\n".RST;

if (strtolower($input) == "c")
	$using_c = true;

if ($using_c)
{

	# -------------------------------------------------------------------------
	#   Phase 2.5: Ask for GüT
	# -------------------------------------------------------------------------

	$add_gut = false;
	echo PMT."Would you like to install the GüT unit testing framework? y/n\n".RST;
	$add_gut = get_yes_no();
	if ($add_gut)
		echo GRN."Ok, we'll install GüT later.\n".RST;
	else
		echo GRN."Be sure to test thoroughly!\n".RST;

	# -------------------------------------------------------------------------
	#   Phase 3: Begin Building Makefile
	# -------------------------------------------------------------------------

	//Start building makefile
	$makefile_text = "";

	$makefile_text .= "NAME=$project_name\n";
	$makefile_text .= "SRC=\n";
	$makefile_text .= "SRC_MAIN=src/main.c\n";
	$makefile_text .= "INC=-I inc\n";
	$makefile_text .= "CC=clang\n";
	$makefile_text .= "CFLAGS=-Wall -Wextra -Werror\n";
	$makefile_text .= "LIB=";
	
	$lib_targets = "";
	$gitignore_text = "";
	$lib_clean_adds = "";
	$lib_fclean_adds = "";
	
	# -------------------------------------------------------------------------
	#   Phase 4: Get External Libraries
	# -------------------------------------------------------------------------

	//Get included libraries
	$another = true;
	$dependtext = "";
	while ($another)
	{
		echo PMT."Enter repo url of a library to include, or nothing for no further libraries\n".RST;
		$url = get_valid_repo_or_empty_string();
		if ($url != "")
		{
			if (!file_exists("lib"))
				mkdir("lib");
			echo PMT."Enter the name of this library:\n".RST;
			$libdirname = get_input_nospaces();
			echo PMT."Enter the name of the static library file that it creates (default: lib$libdirname.a)\n".RST;
			$lname = get_libfilename($libdirname);
			$libfilename = "lib$lname.a";

			//We have everything we need for this library, now add it
			echo GRN."Cloning repo... ".RST;
			system("git clone $url lib/$libdirname > /dev/null 2>&1 0>&1");
			echo GRN."done.\n".RST;

			$lib_targets .= "lib/$libdirname/$libfilename:\n";
			$lib_targets .= "\t@make -C lib/$libdirname\n\n";
			$makefile_text .= "-L lib/$libdirname -l$lname ";
			echo PMT."Add this library to .gitignore? y/n\n".RST;
			if (get_yes_no()) 
				$gitignore_text .= "lib/$libdirname/\n";
			echo PMT."Do you wish to strip this library's repo connection (remove .git)? y/n\n".RST;
			echo GRN."    no  => this library will be an embedded repo.\n".RST;
			echo GRN."    yes => this library can be pushed to your project repo.\n".RST;
			if (get_yes_no())
			{
				echo GRN."Ok, this library will not be an embedded repo and can be pushed.\n".RST;
				system("rm -rf lib/$libdirname/.git");
			}
			else
				echo GRN."Ok, this library will be an embedded repo for this project.\n".RST;
			$dependtext .= "lib/$libdirname/$libfilename ";
			$lib_clean_adds .= "\t@make -C lib/$libdirname/ clean\n";
			$lib_fclean_adds .= "\t@make -C lib/$libdirname/ fclean\n";

		}
		else
			$another = false;
	}
	$makefile_text .= "\n";
	$makefile_text .= "DEPEND=$dependtext\n\n";

	# -------------------------------------------------------------------------
	#   Phase 5: Set up project folders
	# -------------------------------------------------------------------------
	
	mkdir("src");
	mkdir("inc");
	
	# -------------------------------------------------------------------------
	#   Phase 6: Add Makefile Targets
	# -------------------------------------------------------------------------
	
	$makefile_text .= "all: \$(NAME)\n\n";

	$makefile_text .= "\$(NAME): \$(SRC) \$(SRC_MAIN) \$(DEPEND)\n";
	$makefile_text .= "\t\$(CC) \$(CFLAGS) -o \$(NAME) \$(INC) \$(LIB) \$(SRC) \$(SRC_MAIN)\n\n";

	$makefile_text .= "clean:\n";
	if ($add_gut) $makefile_text .= "\t@make gut_clean\n";
	$makefile_text .= $lib_clean_adds;
	$makefile_text .= "\n\n";

	$makefile_text .= "fclean:\n\t@rm -f \$(NAME)\n";
	if ($add_gut) $makefile_text .= "\t@make gut_fclean\n";
	$makefile_text .= $lib_fclean_adds;
	$makefile_text .= "\n";

	$makefile_text .= "re:\n\t@make fclean\n\t@make all\n";
	$makefile_text .= "\n";

	$makefile_text .= $lib_targets;
	
	# -------------------------------------------------------------------------
	#   Phase 7: Create Files
	# -------------------------------------------------------------------------
	
	//file_put_contents seems to depend on the working directy being in the same place it started
	chdir("..");

	file_put_contents("$project_name/Makefile", $makefile_text);
	echo GRN."Makefile made.\n".RST;

	file_put_contents("$project_name/src/main.c",
		"#include <unistd.h>\n\nint main(void)\n{\n\twrite(1, \"Hello, World!\\n\", 17);\n\treturn (0);\n}");
	echo GRN."main.c made.\n".RST;

	file_put_contents("$project_name/.gitignore", $gitignore_text);
	echo GRN.".gitignore made.\n".RST;
	
	# -------------------------------------------------------------------------
	#   Phase 8: Install GüT
	# -------------------------------------------------------------------------

	if ($add_gut)
	{
		echo GRN."Installing GüT...".RST;
		system("git clone https://github.com/gavinfielder/gut.git $project_name/gut > /dev/null 2>&1 0>&1");
		chdir("$project_name/gut");
		exec("sh install.sh > /dev/null 2>&1 0>&1");
		echo GRN."done.\n".RST;
	}
	
} //end if($using_c)A

# -----------------------------------------------------------------------------
#   Done
# -----------------------------------------------------------------------------
	
echo GRN."Project Created Successfully.\n".RST;
	
?>
