<% if PackVersion %>
	<% with PackVersion %>
		<h2>$Pack.Name - $Version</h2>
		<pre>$EditedChangelog.Raw</pre>
	<% end_with %>
<% else_if PackVersions %>
	<ul>
	<% loop PackVersions %>
		<li><a href="$Top.Link$Pack.ID/$ID">$Version</a></li>
	<% end_loop %>
	</ul>
<% else %>
	<ul>
		<% loop Packs %>
			<li><a href="$Top.Link$ID">$Name</a></li>
		<% end_loop %>
	</ul>
<% end_if %>
