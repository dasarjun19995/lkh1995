"use client";

import { Button } from "@/components/ui/button";
import { ExternalLink, Printer } from "lucide-react";

export type ReceiptData = {
  applicationId: string;
  applicationType: string;
  applicantName: string;
  companyName?: string;
  dateSubmitted: string;
  submissionMethod: string;
  status: string;
  email?: string;
};

export function SubmissionReceipt({
  data,
  onStartNew,
}: {
  data: ReceiptData;
  onStartNew: () => void;
}) {
  const print = () => {
    window.print();
  };

  return (
    <div className="min-h-screen bg-[#f5f7fa] px-4 py-10">
      <div id="print-area" className="max-w-4xl mx-auto bg-white shadow-lg border border-slate-200 rounded-xl overflow-hidden">
        <div className="px-8 py-10">
          <h1 className="text-2xl font-bold text-center">Application Submission Receipt</h1>

          <p className="mt-6 text-sm leading-relaxed text-slate-700">
            This document confirms that your bank account application has been successfully received by Prominence Bank.
            Our compliance department will review the submitted information and supporting documentation. You will be
            notified by email once the review process is completed.
          </p>

          <div className="mt-10 overflow-hidden rounded-lg border border-slate-200">
            <table className="w-full text-sm">
              <tbody>
                <tr className="bg-slate-50">
                  <td className="w-1/3 px-4 py-3 font-semibold text-slate-600">Application ID</td>
                  <td className="px-4 py-3 text-slate-800">{data.applicationId}</td>
                </tr>
                <tr>
                  <td className="px-4 py-3 font-semibold text-slate-600">Application Type</td>
                  <td className="px-4 py-3 text-slate-800">{data.applicationType}</td>
                </tr>
                <tr className="bg-slate-50">
                  <td className="px-4 py-3 font-semibold text-slate-600">Applicant Name</td>
                  <td className="px-4 py-3 text-slate-800">{data.applicantName}</td>
                </tr>
                {data.companyName ? (
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-600">Company Name (if Corporate)</td>
                    <td className="px-4 py-3 text-slate-800">{data.companyName}</td>
                  </tr>
                ) : null}
                <tr className="bg-slate-50">
                  <td className="px-4 py-3 font-semibold text-slate-600">Date Submitted</td>
                  <td className="px-4 py-3 text-slate-800">{data.dateSubmitted}</td>
                </tr>
                <tr>
                  <td className="px-4 py-3 font-semibold text-slate-600">Submission Method</td>
                  <td className="px-4 py-3 text-slate-800">{data.submissionMethod}</td>
                </tr>
                <tr className="bg-slate-50">
                  <td className="px-4 py-3 font-semibold text-slate-600">Status</td>
                  <td className="px-4 py-3 text-slate-800">{data.status}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="mt-10 rounded-lg bg-slate-50 border border-slate-200 p-6 text-sm leading-relaxed text-slate-700">
            <p className="font-semibold">Important:</p>
            <p className="mt-2">
              Please retain this receipt for your records. The Application ID above will be required for any communication
              with our onboarding department regarding this application.
            </p>
            <p className="mt-6">Prominence Bank Corp. – Client Onboarding Department</p>
            <p className="mt-2">Secure Client Portal: https://my-account.prominencebank.com/login</p>
            <p className="mt-2">For inquiries, please reference your Application ID.</p>
          </div>

          <div className="mt-8 flex flex-col gap-3 items-center sm:flex-row sm:justify-center no-print">
            <Button variant="outline" size="sm" onClick={print} className="gap-2">
              <Printer className="w-4 h-4" /> Print Receipt
            </Button>
            <Button variant="secondary" size="sm" onClick={onStartNew} className="gap-2">
              <ExternalLink className="w-4 h-4" /> New Application
            </Button>
          </div>
        </div>
      </div>

      <style jsx global>{`
        @media print {
          body, body * {
            visibility: hidden !important;
          }
          #print-area, #print-area * {
            visibility: visible !important;
          }
          #print-area {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 0 !important;
          }
          .no-print {
            display: none !important;
          }
        }
      `}</style>
    </div>
  );
}
