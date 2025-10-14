# Changelog v2.1.0
**September 2025**

This version introduces several new features, enhancements, and bug fixes.

Here is a summary of the latest changes in HAWKI:

## What's New

1. **Attachments** <br>
    Users can now add **PDFs**, **Word documents**, and **images** as attachments to chat messages. Files can be shared with other room members or sent to AI models as context, depending on model capabilities.

2. **New File Storage System**<br>
   HAWKI now features a completely new file storage system. In addition to local storage, HAWKI supports SFTP, S3, and Nextcloud connectivity.

3. **File Converter**<br> 
   Since AI models cannot directly process document files, HAWKI now uses a new file conversion system to generate text-based context for AI models. You can either host the HAWKI Toolkit yourself or use the GWDG-hosted "Docling" service.

4. **Backup System**<br>
   To ensure the security and integrity of your database data, HAWKI now includes a built-in backup system. You can activate scheduled backups and set the backup interval; HAWKI will automatically back up the database at regular intervals.

5. **Scheduler**
    As mentioned above HAWKI has now a schedule system as well. By running the scheduler service, HAWKI automatically takes care of Backups, checking model statuses, and garbage collection.

6. **Announcements**<br> 
   To keep users informed about the latest news, updates, or policy changes, you can now create Announcements. Users will see these as soon as they open HAWKI.

## Quality of Life Improvements

We have received extensive feedback from our growing community and are committed to integrating your suggestions as quickly as possible. Here are some key improvements in HAWKI 2.1.0:

- **Model Filtering**  
  Using toolset buttons on input fields, users can now view filtered lists of models based on features such as file upload, vision, and Google Search.


- **Hidden Fields**  
  To enhance security, input fields containing passwords, data keys, etc., are now hidden. A custom hiding function also prevents browsers from misidentifying these fields as standard login fields.


- **Customization**  
  As always, our goal is to make HAWKI as flexible as possible for every need. This version adds several new environment variables, enabling users to control various configuration options.


- **Improved Broadcasting**  
  The new broadcasting logic allows chat rooms to instantly handle unlimited message sizes.

As HAWKI evolves, the codebase continues to grow. We remain committed to providing a well-structured foundation for our developer community. With this release, we have carefully restructured the codebase to facilitate further development. Although these changes may require adjustments for projects based on previous versions, they are intended to create a more sustainable and modular system for the future.
