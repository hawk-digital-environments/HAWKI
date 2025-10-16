<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MailTemplateSeeder extends Seeder
{

    public function run(): void
    {
        // Clear existing templates
        \DB::table('mail_templates')->truncate();

        // Insert modern mail templates with HAWKI branding
        \DB::table('mail_templates')->insert([
            // Welcome Templates
            [
                'type' => 'welcome',
                'language' => 'en',
                'description' => 'Welcome email for new users',
                'subject' => 'Welcome to {{app_name}} - Your AI Journey Begins!',
                'body' => $this->getWelcomeTemplateEn(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'welcome',
                'language' => 'de',
                'description' => 'Willkommens-E-Mail für neue Benutzer',
                'subject' => 'Willkommen bei {{app_name}} - Ihre KI-Reise beginnt!',
                'body' => $this->getWelcomeTemplateDe(),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // OTP Templates
            [
                'type' => 'otp',
                'language' => 'en',
                'description' => 'Authentication code email',
                'subject' => 'Your {{app_name}} Authentication Code',
                'body' => $this->getOtpTemplateEn(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'otp',
                'language' => 'de',
                'description' => 'Authentifizierungscode E-Mail',
                'subject' => 'Ihr {{app_name}} Authentifizierungscode',
                'body' => $this->getOtpTemplateDe(),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Invitation Templates
            [
                'type' => 'invitation',
                'language' => 'en',
                'description' => 'Group chat invitation email',
                'subject' => 'You\'re invited to join a {{app_name}} Group Chat',
                'body' => $this->getInvitationTemplateEn(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'invitation',
                'language' => 'de',
                'description' => 'Gruppen-Chat Einladungs-E-Mail',
                'subject' => 'Einladung zu einem {{app_name}} Gruppen-Chat',
                'body' => $this->getInvitationTemplateDe(),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Notification Templates
            [
                'type' => 'notification',
                'language' => 'en',
                'description' => 'General notification email',
                'subject' => '{{app_name}} Notification',
                'body' => $this->getNotificationTemplateEn(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'notification',
                'language' => 'de',
                'description' => 'Allgemeine Benachrichtigungs-E-Mail',
                'subject' => '{{app_name}} Benachrichtigung',
                'body' => $this->getNotificationTemplateDe(),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // User Approval Templates
            [
                'type' => 'approval',
                'language' => 'en',
                'description' => 'User registration confirmation email',
                'subject' => 'Account Created Successfully - Welcome to {{app_name}}!',
                'body' => $this->getApprovalTemplateEn(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'approval',
                'language' => 'de',
                'description' => 'Bestätigungs-E-Mail für Benutzerregistrierung',
                'subject' => 'Konto erfolgreich erstellt - Willkommen bei {{app_name}}!',
                'body' => $this->getApprovalTemplateDe(),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Approval Pending Templates
            [
                'type' => 'approval_pending',
                'language' => 'en',
                'description' => 'Account pending approval notification',
                'subject' => 'Your {{app_name}} Account is Pending Approval',
                'body' => $this->getApprovalPendingTemplateEn(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'approval_pending',
                'language' => 'de',
                'description' => 'Benachrichtigung über ausstehende Kontogenehmigung',
                'subject' => 'Ihr {{app_name}}-Account wurde erfolgreich beantragt',
                'body' => $this->getApprovalPendingTemplateDe(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function getWelcomeTemplateEn(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Welcome to {{app_name}}! 🎉</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hello {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    We\'re thrilled to have you join our community of researchers, students, and educators who are exploring the possibilities of generative AI in academic environments.
                </p>

                <div style="background: #dcfce7; border: 1px solid #16a34a; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #15803d;">Your account is now active!</strong><br>
                    <span style="color: #166534;">You can start using HAWKI\'s powerful AI features right away.</span>
                </div>

                <h3 style="color: #1f2937; margin: 24px 0 16px 0;">What can you do with {{app_name}}?</h3>
                
                <ul style="color: #64748b; margin: 16px 0 24px 20px; line-height: 1.7;">
                    <li><strong>1:1 AI Conversations:</strong> Have private, encrypted conversations with advanced AI models</li>
                    <li><strong>Group Chat Rooms:</strong> Collaborate with colleagues in AI-enhanced group discussions</li>
                    <li><strong>Multi-Model Support:</strong> Access various AI models including OpenAI, Google, and local options</li>
                    <li><strong>Privacy-First Design:</strong> Your conversations are protected with end-to-end encryption</li>
                    <li><strong>Academic Focus:</strong> Tools and features designed specifically for university environments</li>
                </ul>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{app_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        Start Using {{app_name}}
                    </a>
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    If you have any questions or need assistance, don\'t hesitate to reach out to our support team at <a href="mailto:{{support_email}}" style="color: #2563eb; text-decoration: none;">{{support_email}}</a> or explore <a href="https://www.hawki.info/" target="_blank" style="color: #2563eb; text-decoration: none;">our documentation</a>.
                </p>

                <p style="font-size: 16px; color: #64748b;">
                    Welcome aboard!<br>
                    <strong>The {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getWelcomeTemplateDe(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Willkommen bei {{app_name}}! 🎉</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hallo {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Wir freuen uns sehr, Sie in unserer Gemeinschaft von Forschern, Studenten und Lehrenden begrüßen zu dürfen, die die Möglichkeiten generativer KI in universitären Umgebungen erkunden.
                </p>

                <div style="background: #dcfce7; border: 1px solid #16a34a; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #15803d;">Ihr Konto ist jetzt aktiv!</strong><br>
                    <span style="color: #166534;">Sie können sofort mit der Nutzung von {{app_name}}s leistungsstarken KI-Funktionen beginnen.</span>
                </div>

                <h3 style="color: #1f2937; margin: 24px 0 16px 0;">Was können Sie mit {{app_name}} machen?</h3>
                
                <ul style="color: #64748b; margin: 16px 0 24px 20px; line-height: 1.7;">
                    <li><strong>1:1 KI-Unterhaltungen:</strong> Private, verschlüsselte Gespräche mit fortschrittlichen KI-Modellen</li>
                    <li><strong>Gruppen-Chat-Räume:</strong> Zusammenarbeit mit Kollegen in KI-unterstützten Gruppendiskussionen</li>
                    <li><strong>Multi-Modell-Unterstützung:</strong> Zugang zu verschiedenen KI-Modellen einschließlich OpenAI, Google und lokalen Optionen</li>
                    <li><strong>Datenschutz-orientiertes Design:</strong> Ihre Unterhaltungen sind durch End-to-End-Verschlüsselung geschützt</li>
                    <li><strong>Akademischer Fokus:</strong> Tools und Funktionen speziell für universitäre Umgebungen entwickelt und in stetiger Weiterentwicklung</li>
                </ul>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{app_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        {{app_name}} nutzen
                    </a>
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Wenn Sie Fragen haben oder Unterstützung benötigen, zögern Sie nicht, unser Support-Team unter <a href="mailto:{{support_email}}" style="color: #2563eb; text-decoration: none;">{{support_email}}</a> zu kontaktieren oder <a href="https://www.hawki.info/" target="_blank" style="color: #2563eb; text-decoration: none;">unsere Dokumentation</a> zu erkunden.
                </p>

                <p style="font-size: 16px; color: #64748b;">
                    Willkommen an Bord!<br>
                    <strong>Das {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getOtpTemplateEn(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Your Authentication Code</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hello {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    You\'ve requested secure access to your {{app_name}} account. Please use the authentication code below to complete your login:
                </p>

                <div style="text-align: center; margin: 32px 0;">
                    <div style="background: #f8fafc; border: 2px solid #2563eb; border-radius: 12px; padding: 24px; display: inline-block;">
                        <div style="font-size: 36px; font-weight: 700; color: #2563eb; letter-spacing: 8px; font-family: \'Courier New\', monospace;">{{otp}}</div>
                    </div>
                </div>

                <div style="background: #fef3c7; border: 1px solid #d97706; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #92400e;">🔒 Security Notice</strong><br>
                    <span style="color: #a16207;">• This code expires in 5 minutes<br>
                    • Never share this code with anyone<br>
                    • {{app_name}} staff will never ask for this code</span>
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    <strong>Didn\'t request this code?</strong><br>
                    If you didn\'t try to log in to {{app_name}}, please ignore this email. Your account remains secure.
                </p>

                <div style="background: #dbeafe; border: 1px solid #2563eb; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #1d4ed8;">💡 Pro Tip:</strong><br>
                    <span style="color: #1e40af;">For faster and more secure access, explore the security settings in your {{app_name}} account after logging in.</span>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Stay secure,<br>
                    <strong>The {{app_name}} Security Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getOtpTemplateDe(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Ihr Authentifizierungscode</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hallo {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Sie haben sicheren Zugang zu Ihrem {{app_name}}-Konto angefordert. Verwenden Sie bitte den untenstehenden Authentifizierungscode, um Ihre Anmeldung abzuschließen:
                </p>

                <div style="text-align: center; margin: 32px 0;">
                    <div style="background: #f8fafc; border: 2px solid #2563eb; border-radius: 12px; padding: 24px; display: inline-block;">
                        <div style="font-size: 36px; font-weight: 700; color: #2563eb; letter-spacing: 8px; font-family: \'Courier New\', monospace;">{{otp}}</div>
                    </div>
                </div>

                <div style="background: #fef3c7; border: 1px solid #d97706; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #92400e;">🔒 Sicherheitshinweis</strong><br>
                    <span style="color: #a16207;">• Dieser Code läuft in 5 Minuten ab<br>
                    • Teilen Sie diesen Code niemals mit anderen<br>
                    • {{app_name}}-Mitarbeiter werden niemals nach diesem Code fragen</span>
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    <strong>Haben Sie diesen Code nicht angefordert?</strong><br>
                    Wenn Sie nicht versucht haben, sich bei {{app_name}} anzumelden, ignorieren Sie diese E-Mail bitte. Ihr Konto bleibt sicher.
                </p>

                <div style="background: #dbeafe; border: 1px solid #2563eb; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #1d4ed8;">💡 Profi-Tipp:</strong><br>
                    <span style="color: #1e40af;">Für schnelleren und sichereren Zugang erkunden Sie nach der Anmeldung die Sicherheitseinstellungen in Ihrem {{app_name}}-Konto.</span>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Bleiben Sie sicher,<br>
                    <strong>Das {{app_name}} Sicherheitsteam</strong>
                </p>
            </div>
        </div>';
    }

    private function getInvitationTemplateEn(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">You\'re invited to collaborate! 🚀</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hello there!
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    <strong>{{inviter_name}}</strong> has invited you to join an exciting group chat conversation on {{app_name}}, where you can collaborate using advanced AI technology.
                </p>

                <div style="background: #dbeafe; border: 1px solid #2563eb; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #1d4ed8;">Group Chat:</strong> {{room_name}}<br>
                    <strong style="color: #1d4ed8;">About:</strong> {{room_description}}
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    {{app_name}} is a privacy-focused platform that enables university communities to harness the power of generative AI for research, learning, and collaboration. Your conversations are protected with end-to-end encryption.
                </p>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{invitation_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        Join the Conversation
                    </a>
                </div>

                <div style="background: #fef3c7; border: 1px solid #d97706; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #92400e;">⏰ Time-sensitive invitation</strong><br>
                    <span style="color: #a16207;">This invitation link will expire in 48 hours for security reasons. Click the button above to join now.<br>
                    <small>Sent on {{current_date}}</small></span>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Looking forward to seeing you in the conversation!<br>
                    <strong>The {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getInvitationTemplateDe(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Sie sind zur Zusammenarbeit eingeladen! 🚀</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hallo!
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    <strong>{{inviter_name}}</strong> hat Sie zu einem spannenden Gruppen-Chat auf {{app_name}} eingeladen, wo Sie mit fortschrittlicher KI-Technologie zusammenarbeiten können.
                </p>

                <div style="background: #dbeafe; border: 1px solid #2563eb; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #1d4ed8;">Gruppen-Chat:</strong> {{room_name}}<br>
                    <strong style="color: #1d4ed8;">Beschreibung:</strong> {{room_description}}
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    {{app_name}} ist eine datenschutzorientierte Plattform, die universitären Gemeinschaften ermöglicht, die Kraft generativer KI für Forschung, Lernen und Zusammenarbeit zu nutzen. Ihre Unterhaltungen sind durch End-to-End-Verschlüsselung geschützt.
                </p>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{invitation_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        An der Unterhaltung teilnehmen
                    </a>
                </div>

                <div style="background: #fef3c7; border: 1px solid #d97706; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #92400e;">⏰ Zeitkritische Einladung</strong><br>
                    <span style="color: #a16207;">Dieser Einladungslink läuft aus Sicherheitsgründen in 48 Stunden ab. Klicken Sie jetzt auf die Schaltfläche oben, um teilzunehmen.<br>
                    <small>Gesendet am {{current_date}}</small></span>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Wir freuen uns darauf, Sie in der Unterhaltung zu sehen!<br>
                    <strong>Das {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getNotificationTemplateEn(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">{{notification_title}}</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hello {{user_name}},
                </p>
                
                <div style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    {{notification_message}}
                </div>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{action_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        {{action_text}}
                    </a>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Best regards,<br>
                    <strong>The {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getNotificationTemplateDe(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">{{notification_title}}</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hallo {{user_name}},
                </p>
                
                <div style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    {{notification_message}}
                </div>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{action_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        {{action_text}}
                    </a>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Mit freundlichen Grüßen,<br>
                    <strong>Das {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getApprovalTemplateEn(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Welcome to {{app_name}}! 🎉</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hello {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Your account has been successfully created and activated.
                </p>

                <div style="background: #dcfce7; border: 1px solid #16a34a; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #15803d;">✅ Registration Complete!</strong><br>
                    <span style="color: #166534;">You can now access all {{app_name}} features and start exploring generative AI capabilities.</span>
                </div>

                <h3 style="color: #1f2937; margin: 24px 0 16px 0;">Next Steps:</h3>
                
                <ul style="color: #64748b; margin: 16px 0 24px 20px; line-height: 1.7;">
                    <li><strong>Complete Your Profile:</strong> Add your bio and customize your settings</li>
                    <li><strong>Set Up Security:</strong> Enable additional security features for enhanced account protection</li>
                    <li><strong>Start Chatting:</strong> Begin your first AI conversation or join a group chat</li>
                    <li><strong>Explore Features:</strong> Discover different AI models and collaboration tools</li>
                </ul>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{app_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        Access Your {{app_name}} Account
                    </a>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Welcome aboard!<br>
                    <strong>The {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getApprovalTemplateDe(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Willkommen bei {{app_name}}! 🎉</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hallo {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Ihr Konto wurde erfolgreich erstellt und aktiviert.
                </p>

                <div style="background: #dcfce7; border: 1px solid #16a34a; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #15803d;">✅ Registrierung abgeschlossen!</strong><br>
                    <span style="color: #166534;">Sie können jetzt auf alle {{app_name}}-Funktionen zugreifen und generative KI-Fähigkeiten erkunden.</span>
                </div>

                <h3 style="color: #1f2937; margin: 24px 0 16px 0;">Nächste Schritte:</h3>
                
                <ul style="color: #64748b; margin: 16px 0 24px 20px; line-height: 1.7;">
                    <li><strong>Vervollständigen Sie Ihr Profil:</strong> Fügen Sie Ihre Biografie hinzu und passen Sie Ihre Einstellungen an</li>
                    <li><strong>Sicherheit einrichten:</strong> Aktivieren Sie zusätzliche Sicherheitsfeatures für erhöhten Kontoschutz</li>
                    <li><strong>Mit Chatten beginnen:</strong> Starten Sie Ihr erstes KI-Gespräch oder treten Sie einem Gruppen-Chat bei</li>
                    <li><strong>Funktionen erkunden:</strong> Entdecken Sie verschiedene KI-Modelle und Kollaborationstools</li>
                </ul>

                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{app_url}}" style="background: #2563eb; color: white; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; display: inline-block;">
                        Auf Ihr {{app_name}}-Konto zugreifen
                    </a>
                </div>

                <p style="font-size: 16px; color: #64748b;">
                    Willkommen an Bord!<br>
                    <strong>Das {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getApprovalPendingTemplateEn(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Account Request Received 📋</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hello {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Thank you for your interest in {{app_name}}! Your account request has been successfully submitted and is currently being reviewed by our team.
                </p>

                <div style="background: #fef3c7; border: 1px solid #d97706; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #92400e;">⏳ Pending Approval</strong><br>
                    <span style="color: #a16207;">Your account is awaiting approval. You will receive a notification email once your account has been activated.</span>
                </div>

                <h3 style="color: #1f2937; margin: 24px 0 16px 0;">What happens next?</h3>
                
                <ul style="color: #64748b; margin: 16px 0 24px 20px; line-height: 1.7;">
                    <li><strong>Review Process:</strong> Our team will review your account request</li>
                    <li><strong>Email Notification:</strong> You\'ll receive an email when your account is activated</li>
                    <li><strong>Access Granted:</strong> Once approved, you can start using all {{app_name}} features</li>
                </ul>

                <div style="background: #dbeafe; border: 1px solid #2563eb; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #1d4ed8;">💡 Please Note:</strong><br>
                    <span style="color: #1e40af;">The approval process typically takes 1-2 business days. If you have any questions, please don\'t hesitate to contact our support team.</span>
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    If you have any questions or need assistance, feel free to reach out to our support team at <a href="mailto:{{support_email}}" style="color: #2563eb; text-decoration: none;">{{support_email}}</a> or explore <a href="https://www.hawki.info/" target="_blank" style="color: #2563eb; text-decoration: none;">our documentation</a>.
                </p>

                <p style="font-size: 16px; color: #64748b;">
                    We look forward to welcoming you soon!<br>
                    <strong>The {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }

    private function getApprovalPendingTemplateDe(): string
    {
        return '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2937;">
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #000000;">Account-Antrag erhalten 📋</h1>
            </div>
            
            <div style="padding: 32px; background: #ffffff;">
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Hallo {{user_name}},
                </p>
                
                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Vielen Dank für Ihr Interesse an {{app_name}}! Ihr Account wurde erfolgreich beantragt und wird derzeit von unserem Team überprüft.
                </p>

                <div style="background: #fef3c7; border: 1px solid #d97706; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #92400e;">⏳ Genehmigung ausstehend</strong><br>
                    <span style="color: #a16207;">Ihr Account wartet auf Freischaltung. Sie erhalten eine Benachrichtigung per E-Mail, sobald Ihr Account aktiviert wurde.</span>
                </div>

                <h3 style="color: #1f2937; margin: 24px 0 16px 0;">Wie geht es weiter?</h3>
                
                <ul style="color: #64748b; margin: 16px 0 24px 20px; line-height: 1.7;">
                    <li><strong>Überprüfung:</strong> Unser Team überprüft Ihren Account-Antrag</li>
                    <li><strong>E-Mail-Benachrichtigung:</strong> Sie erhalten eine E-Mail, sobald Ihr Account freigeschaltet wurde</li>
                    <li><strong>Zugang gewährt:</strong> Nach der Freischaltung können Sie alle {{app_name}}-Funktionen nutzen</li>
                </ul>

                <div style="background: #dbeafe; border: 1px solid #2563eb; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <strong style="color: #1d4ed8;">💡 Bitte beachten:</strong><br>
                    <span style="color: #1e40af;">Der Freischaltungsprozess dauert in der Regel 1-2 Werktage. Bei Fragen wenden Sie sich gerne an unser Support-Team.</span>
                </div>

                <p style="font-size: 16px; color: #64748b; margin-bottom: 24px;">
                    Wenn Sie Fragen haben oder Unterstützung benötigen, zögern Sie nicht, unser Support-Team unter <a href="mailto:{{support_email}}" style="color: #2563eb; text-decoration: none;">{{support_email}}</a> zu kontaktieren oder <a href="https://www.hawki.info/" target="_blank" style="color: #2563eb; text-decoration: none;">unsere Dokumentation</a> zu erkunden.
                </p>

                <p style="font-size: 16px; color: #64748b;">
                    Wir freuen uns darauf, Sie bald willkommen zu heißen!<br>
                    <strong>Das {{app_name}} Team</strong>
                </p>
            </div>
        </div>';
    }
}
