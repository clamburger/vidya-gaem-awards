<header class="jumbotron masthead">
    <div class="inner">
        <h1>2014 Vidya Gaem Awards</h1>
        <p class="implying">>implying you're opinion is worth shit</p>
    </div>
</header>
<div class="row">
    <div class="span5">
        <h1>News <small><a href="/news">view all</a></small></h1>
        <ul class="news">
            <loop:news>
            <li class="<tag:news[].Class />">
                <if:news[].New><span class="label label-info">New</span></if:news[].New>
                <strong><tag:news[].Date /></strong>: <tag:news[].Text />
            </li>
            </loop:news>
        </ul>
    </div>
    <div class="span6 offset1">
        <if:false>
        <h1>Register your interest</h1>
        If you're interesting in helping out, fill out our <a href="https://docs.google.com/forms/d/1-2iPZhlvMvZgYS8SRVOZYfyZvtaK_wtuem4vWXKmHMs/viewform?usp=send_form">expression of interest</a> form.
        </if:false>
        <h1>Why this page still exist</h1>
        It's pretty useless.
    </div>
</div>

<a href="https://plus.google.com/111295979980964577432" rel="publisher">Google+</a>
