=== Hostelworld Trailhead ===
Version: 0.2.0

The blog's content pipeline inside WordPress: opportunities, research,
ideas, briefs, drafts and a refresh schedule. Menu: Trailhead.

Claude does the writing through a queue. Each request (research, a brief,
a draft, a refresh brief) becomes a private "Trailhead task". A scheduled
task on the editor's own Claude account reads the queue through the
WordPress connector, does the work (with the Ahrefs connector where
needed), and writes the answer back into the task. Trailhead then puts it
in place. Claude only ever changes Trailhead tasks: Trailhead itself
creates drafts, and nothing is ever published except by a person.

Needs Hostelworld Content Health (active, with at least one sync) for
Search Console clicks, impressions, positions and searches.

== Changelog ==

= 0.4.1 =
* Refresh schedule: change the Every column for as many articles as you like, then click Save changes at the bottom. The page no longer reloads after each change, and you stay on the same tab, filter, sort and page.

= 0.4.0 =
* Works with Content Health: Queue for refresh there puts the article on Trailhead's refresh list for the morning batch; every refresh sends Claude the article's Content Health brief; the Content planner's Start in Trailhead opens New article filled in; finished refreshes show up in Content Health's Refresh impact, measured from the latest refresh.

= 0.3.0 =
* Ahrefs API key in Settings: WordPress fetches the Ahrefs data for research, competitor gaps and briefs itself, so Claude only does the thinking. Units used per run are listed in Settings. Falls back to the connector without a key.
* Research: Show keywords now lists the Ahrefs keywords and competitor rankings straight away, with where we already rank; then send them to Claude for ideas.
* New article has its own menu item under Create.
* Blocks library finds every shortcode the blog's plugins provide, with real examples from published articles. Claude uses only those, with the exact syntax.
* Photos: when a draft arrives, every image slot gets the best Unsplash match (Pexels if nothing is found), with credit. Swap any photo from the draft review.
* Refreshes: Claude writes the brief and the updated article. Review what changed, ask for changes, then Open in editor loads the update into the Classic Editor. Nothing goes live until you click Update, which also marks the article refreshed.

0.2.0
- Start an article straight from a target keyword, with an author voice,
  a direction and your own input (interviews, quotes, notes).
- Playbooks: your process for each step (research, information gain,
  outlining, drafting, product placement, images, internal linking,
  formatting), sent to Claude with the work it applies to.
- Hostelworld facts: the source of truth about Hostelworld and its
  products. Authors and voices.
- Blocks and live data: every shortcode on the site is found
  automatically. Switch on the ones Claude may use, with when to use them
  and a working example; drafts include them.
- Information gain: briefs list what the article adds with source URLs;
  Claude uses web search, Ahrefs and your notes.
- Images: search Unsplash and Pexels for each image slot in a draft and
  place a photo with the photographer credit in one click.
- Self-updating content: monthly interval, a standing "every refresh"
  brief per article, and refresh briefs queued automatically each
  morning for articles that are due.

0.1.0
- First version: Today, Research (opportunities from Search Console and
  research by topic), Ideas, Briefs, Drafts with fact checks, Refresh
  schedule with refresh types, Claude queue and Settings.
