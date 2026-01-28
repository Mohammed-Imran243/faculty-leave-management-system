<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" 
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
    xmlns:php="http://php.net/xsl">

    <xsl:template match="/">
        <html>
            <head>
                <style>
                    body { font-family: 'Times New Roman', serif; font-size: 14px; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .header h2, .header h3 { margin: 5px; text-transform: uppercase; }
                    .logo-left { float: left; width: 60px; }
                    .logo-right { float: right; width: 60px; }
                    .title { text-align: center; font-weight: bold; text-decoration: underline; margin: 15px 0; font-size: 16px; }
                    
                    .content-row { display: block; margin-bottom: 15px; clear: both; }
                    .left-col { float: left; width: 45%; }
                    .right-col { float: right; width: 45%; }
                    
                    .underline { text-decoration: underline; }
                    .dotted-line { border-bottom: 1px dotted #000; display: inline-block; min-width: 100px; text-align: center; }
                    
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th, td { border: 1px solid black; padding: 5px; text-align: center; font-size: 12px; }
                    th { background-color: #f0f0f0; }
                    
                    .footer { margin-top: 40px; clear: both; }
                    .footer-left { float: left; width: 40%; text-align: center; }
                    .footer-right { float: right; width: 40%; text-align: center; }
                    .signature-img { height: 40px; display: block; margin: 0 auto; }
                </style>
            </head>
            <body>
                <div class="header">
                    <!-- Placeholders for logos if available -->
                     <!-- <img src="logo_left.png" class="logo-left"/> -->
                     <!-- <img src="logo_right.png" class="logo-right"/> -->
                    <h3>C. ABDUL HAKEEM COLLEGE OF</h3>
                    <h3>ENGINEERING &amp; TECHNOLOGY</h3>
                    <div style="font-size: 12px">Hakeem Nagar, Melvisharam - 632 509.</div>
                </div>

                <div class="title">LEAVE APPLICATION</div>

                <div class="content-row">
                    <div class="left-col">
                        <b>From</b><br/>
                        <span class="dotted-line"><xsl:value-of select="LeaveApplication/Applicant/Name"/></span><br/>
                        <span class="dotted-line"><xsl:value-of select="LeaveApplication/Applicant/Designation"/></span><br/>
                        Department of <span class="dotted-line"><xsl:value-of select="LeaveApplication/Applicant/Department"/></span>
                    </div>
                    <div class="right-col">
                        <b>To</b><br/>
                        The Correspondent / Principal<br/>
                        C. ABDUL HAKEEM COLLEGE OF<br/>
                        ENGINEERING &amp; TECHNOLOGY<br/>
                        Hakeem Nagar, Melvisharam - 632 509.
                    </div>
                </div>

                <div class="content-row" style="margin-top:20px;">
                    Through HOD: <span class="dotted-line"><xsl:value-of select="LeaveApplication/Signatures/HoD/Name"/></span>
                    <span style="float:right">Date: <span class="dotted-line"><xsl:value-of select="LeaveApplication/GeneratedDate"/></span></span>
                </div>

                <div class="content-row" style="margin-top:20px; line-height: 2.0;">
                    Sir,<br/>
                    Kindly grant me <span class="dotted-line"><xsl:value-of select="LeaveApplication/LeaveDetails/Type"/></span> leave for 
                    <span class="dotted-line"><xsl:value-of select="LeaveApplication/LeaveDetails/Duration"/></span> 
                    (day(s)/hours) only, on/from 
                    <span class="dotted-line"><xsl:value-of select="LeaveApplication/LeaveDetails/StartDate"/></span> to 
                    <span class="dotted-line"><xsl:value-of select="LeaveApplication/LeaveDetails/EndDate"/></span>.<br/>
                    Reason: <span class="dotted-line" style="width: 80%"><xsl:value-of select="LeaveApplication/LeaveDetails/Reason"/></span><br/>
                    No. of days leave already availed: <span class="dotted-line"><xsl:value-of select="LeaveApplication/LeaveDetails/DaysAvailed"/></span>
                </div>

                <div class="content-row" style="text-align: right; margin-top:20px; margin-bottom: 20px;">
                    Yours faithfully,<br/>
                    <br/>
                   (Signature of Faculty)
                </div>

                <div class="content-row">
                    <b>Class arrangements made:</b>
                    <table>
                        <thead>
                            <tr>
                                <th>Day &amp; Date</th>
                                <th>Hour</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Arrangement (Substitute)</th>
                                <th>Initials of the Faculty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <xsl:for-each select="LeaveApplication/ClassArrangements/Arrangement">
                                <tr>
                                    <td><xsl:value-of select="Date"/></td>
                                    <td><xsl:value-of select="Hour"/></td>
                                    <td>-</td> <!-- Class info could be added to DB if needed -->
                                    <td>-</td> <!-- Subject info could be added -->
                                    <td><xsl:value-of select="Substitute"/></td>
                                    <td>
                                        <xsl:if test="Status='ACCEPTED'">
                                            <span style="color:green; font-weight:bold;">Accepted</span>
                                        </xsl:if>
                                        <xsl:if test="Status!='ACCEPTED'">
                                            Pending
                                        </xsl:if>
                                    </td>
                                </tr>
                            </xsl:for-each>
                            <xsl:if test="not(LeaveApplication/ClassArrangements/Arrangement)">
                                <tr><td colspan="6">No classes affected / No substitution required.</td></tr>
                            </xsl:if>
                        </tbody>
                    </table>
                </div>

                <div class="footer">
                    <div class="footer-left">
                        <div style="height: 50px;">
                            <xsl:if test="LeaveApplication/Signatures/HoD/Status='APPROVED'">
                                <div style="color:green; border:1px solid green; display:inline-block; padding:5px;">
                                    Digitally Recommended<br/>
                                    <small><xsl:value-of select="LeaveApplication/Signatures/HoD/Timestamp"/></small>
                                </div>
                            </xsl:if>
                        </div>
                        <div><b>HEAD OF THE DEPARTMENT</b></div>
                        (with date)
                    </div>
                     <div class="footer-right">
                        <div style="height: 50px;">
                             <xsl:if test="LeaveApplication/Signatures/Principal/Status='APPROVED'">
                                <div style="color:green; border:1px solid green; display:inline-block; padding:5px;">
                                    Digitally Granted<br/>
                                    <small><xsl:value-of select="LeaveApplication/Signatures/Principal/Timestamp"/></small>
                                </div>
                            </xsl:if>
                        </div>
                        <div><b>CORRESPONDENT / PRINCIPAL</b></div>
                    </div>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
