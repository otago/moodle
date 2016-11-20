<% include SideBar %>
<div class="content-container unit size3of4 lastUnit">
	<article>
		<h1>$Title</h1>
		<div class="content">$Content</div>
		
		<h2>core_user_get_course_user_profiles:</h2>
		<code>
			<% if $UserProfile %>
				<% loop $UserProfile %>
					<h3>$fullname</h3>
					id = $id <br/>
					firstname =  $firstname<br/>
					lastname =  $lastname<br/>
					fullname = $fullname<br/>
					email = $email<br/>
					firstaccess = $firstaccess<br/>
					lastaccess = $lastaccess<br/>
					description =  $description<br/>
					descriptionformat = $descriptionformat<br/>
					city = $city<br/>
					country = $country<br/>
					profileimageurlsmall = $profileimageurlsmall<br/>
					profileimageurl = $profileimageurl<br/>

					<h4>User Courses</h4>
					<ul>
						<% loop $enrolledcourses %>
						<li>
							<h5>$fullname</h5>
							id = $id<br/>
							fullname = $fullname<br/>
							shortname =  $shortname<br/>
						</li>
						<% end_loop %>
					</ul>
				<% end_loop %>
			<% end_if %>
		</code>
		
		<h1>core_enrol_get_users_courses</h1>
		<ul>
		<% loop $Programmes %>
			<li>id = id<br/>
			shortname = $shortname<br/>
			fullname = $fullname<br/>
			enrolledusercount = $enrolledusercount</li>
		<% end_loop %>
		</ul>
		
		<h1>core_user_get_users_by_field</h1>
		<ul>
		<% loop $User %>
			username = $username<br/>
			"firstname":$firstname<br/>
			"lastname":$lastname<br/>
			"fullname":$fullname<br/>
			"email":$email<br/>
			"department":$department<br/>
			"idnumber":$idnumber<br/>
			"firstaccess":$firstaccess<br/>
			"lastaccess":$lastaccess<br/>
			"description":$description<br/>
			"descriptionformat":$descriptionformat<br/>
			"city":$city<br/>
			"country":$country<br/>
			"profileimageurlsmall":$profileimageurlsmall<br/>
			"profileimageurl": $profileimageurl<br/>

			<% loop $preferences %>
				{"name":$name,"value":$value} <br/>
			<% end_loop %>
		<% end_loop %>
		</ul>
	</article>
	$Form
	$PageComments
</div>